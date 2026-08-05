<?php

namespace App\Http\Controllers;

use App\Models\AccessPermissionException;
use App\Models\User;
use App\Services\AccessCatalog;
use App\Services\EffectiveAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class PermissionExceptionController extends Controller
{
    public function __construct(
        private readonly AccessCatalog $catalog,
        private readonly EffectiveAccessService $effectiveAccess,
    ) {}

    public function edit(Request $request, User $user): View
    {
        $this->ensureManageable($request, $user);

        return $this->formView($user);
    }

    public function preview(Request $request, User $user): View
    {
        $this->ensureManageable($request, $user);
        $current = $user->permissions()->pluck('name')->sort()->values();
        $selected = $this->validatedPermissions($request, $user);
        $assignable = collect(array_keys($this->catalog->directAssignablePermissions()));
        $after = collect($selected)->unique()->sort()->values();
        $added = $after->diff($current)->values();
        $removed = $current->diff($after)->values();
        $data = $request->validate([
            'reason' => [Rule::requiredIf($added->isNotEmpty() || $removed->isNotEmpty()), 'nullable', 'string', 'min:10', 'max:1000'],
        ]);
        $payload = [
            'user_id' => $user->id,
            'before' => $current->all(),
            'after' => $after->all(),
            'reason' => trim((string) ($data['reason'] ?? '')),
            'expires_at' => now()->addMinutes(15)->timestamp,
        ];

        return $this->formView($user, [
            'added' => $added->all(),
            'removed' => $removed->all(),
            'before_summary' => $this->effectiveAccess->summary($user),
            'token' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
            'reason' => $payload['reason'],
        ], $after->intersect($assignable)->all());
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureManageable($request, $user);
        $payload = $this->validatedToken($request, $user);

        DB::transaction(function () use ($payload, $request, $user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $current = $lockedUser->permissions()->pluck('name')->sort()->values()->all();
            if ($current !== $payload['before']) {
                throw ValidationException::withMessages([
                    'confirmation' => 'Excepțiile utilizatorului s-au schimbat între timp. Reia previzualizarea.',
                ]);
            }

            $before = collect($payload['before']);
            $after = collect($payload['after']);
            $added = $after->diff($before);
            $removed = $before->diff($after);
            $permissionIds = Permission::query()->whereIn('name', $after)->where('guard_name', 'web')->pluck('id', 'name');
            $lockedUser->syncPermissions($after->all());

            AccessPermissionException::query()
                ->where('user_id', $lockedUser->id)
                ->whereIn('permission_id', Permission::query()->whereIn('name', $removed)->pluck('id'))
                ->delete();
            foreach ($added as $permission) {
                AccessPermissionException::query()->updateOrCreate(
                    ['user_id' => $lockedUser->id, 'permission_id' => $permissionIds[$permission]],
                    [
                        'reason' => $payload['reason'],
                        'granted_by' => $request->user()->id,
                        'updated_by' => $request->user()->id,
                    ],
                );
            }

            activity('access')
                ->performedOn($lockedUser)
                ->causedBy($request->user())
                ->withProperties([
                    'before' => ['direct_permissions' => $payload['before']],
                    'after' => ['direct_permissions' => $payload['after']],
                    'added' => $added->values()->all(),
                    'removed' => $removed->values()->all(),
                    'reason' => $payload['reason'],
                ])
                ->log('Excepții individuale actualizate');
        }, 3);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('access.show', $user)->with('status', 'Excepțiile individuale au fost actualizate.');
    }

    private function formView(User $user, ?array $preview = null, ?array $selected = null): View
    {
        $user->load(['roles', 'permissions', 'permissionExceptions.permission', 'activeManagedLocations']);
        $assignable = array_keys($this->catalog->directAssignablePermissions());

        return view('access.exceptions.edit', [
            'user' => $user,
            'permissionsByModule' => $this->catalog->permissionsByModule()
                ->map(fn ($permissions) => $permissions->filter(fn (array $entry): bool => in_array($entry['ability'], $assignable, true))->values())
                ->filter->isNotEmpty(),
            'selectedPermissions' => $selected ?? $user->permissions->pluck('name')->all(),
            'removalOnlyPermissions' => $user->permissions->pluck('name')->diff($assignable)->values()->all(),
            'contexts' => $user->permissionExceptions->keyBy('permission.name'),
            'preview' => $preview,
        ]);
    }

    /** @return array<int, string> */
    private function validatedPermissions(Request $request, User $user): array
    {
        $allowed = collect(array_keys($this->catalog->directAssignablePermissions()))
            ->merge($user->permissions()->pluck('name'))
            ->unique()
            ->all();
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in($allowed)],
        ]);

        return collect($data['permissions'] ?? [])->sort()->values()->all();
    }

    /** @return array<string, mixed> */
    private function validatedToken(Request $request, User $user): array
    {
        $request->validate(['confirmation_token' => ['required', 'string']]);

        try {
            $payload = json_decode(Crypt::decryptString($request->string('confirmation_token')->toString()), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw ValidationException::withMessages(['confirmation' => 'Previzualizarea nu mai este validă. Reia verificarea.']);
        }

        if (! is_array($payload)
            || (int) ($payload['user_id'] ?? 0) !== $user->id
            || (int) ($payload['expires_at'] ?? 0) < now()->timestamp
            || ! is_array($payload['before'] ?? null)
            || ! is_array($payload['after'] ?? null)
            || ! is_string($payload['reason'] ?? null)) {
            throw ValidationException::withMessages(['confirmation' => 'Previzualizarea a expirat sau nu corespunde utilizatorului.']);
        }

        return $payload;
    }

    private function ensureManageable(Request $request, User $user): void
    {
        abort_unless($request->user()->isProtectedAdministrator(), 403);
        abort_if($user->isProtectedAdministrator() || $user->hasRole('super-admin'), 403, 'Contul protejat nu folosește excepții individuale.');
    }
}
