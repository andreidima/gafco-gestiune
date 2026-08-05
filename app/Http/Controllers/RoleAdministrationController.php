<?php

namespace App\Http\Controllers;

use App\Models\AccessRoleProfile;
use App\Services\AccessCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class RoleAdministrationController extends Controller
{
    public function __construct(private readonly AccessCatalog $catalog) {}

    public function index(Request $request): View
    {
        $this->ensureProtectedActor($request);
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return view('access.roles.index', [
            'roles' => $roles,
            'profiles' => AccessRoleProfile::query()->whereIn('role_id', $roles->pluck('id'))->get()->keyBy('role_id'),
            'catalog' => $this->catalog,
        ]);
    }

    public function create(Request $request): View
    {
        $this->ensureProtectedActor($request);

        return view('access.roles.form', [
            'role' => null,
            'profile' => null,
            'permissionsByModule' => $this->catalog->permissionsByModule(),
            'selectedPermissions' => [],
            'reservedPermissions' => [],
            'preview' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureProtectedActor($request);
        $data = $this->validatedMetadata($request);

        $role = DB::transaction(function () use ($data, $request): Role {
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
            AccessRoleProfile::create([
                'role_id' => $role->id,
                'label' => $data['label'],
                'description' => $data['description'],
                'workspace' => $data['workspace'],
                'requires_locations' => $request->boolean('requires_locations'),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            activity('access')
                ->performedOn($role)
                ->causedBy($request->user())
                ->withProperties(['role' => $role->name, 'label' => $data['label']])
                ->log('Rol personalizat creat');

            return $role;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('access.roles.edit', $role)
            ->with('status', 'Rolul a fost creat. Configurează acum drepturile sale.');
    }

    public function edit(Request $request, Role $role): View
    {
        $this->ensureProtectedActor($request);
        $this->ensureWebRole($role);
        $role->load('permissions');

        return $this->formView($role);
    }

    public function preview(Request $request, Role $role): View
    {
        $this->ensureProtectedActor($request);
        $this->ensureWebRole($role);
        abort_if($role->name === 'super-admin', 403, 'Rolul protejat nu poate fi modificat.');
        $role->load('permissions');

        $selected = $this->validatedPermissions($request);
        $current = $role->permissions->pluck('name')->sort()->values();
        $editable = collect(array_keys($this->editablePermissions()));
        $preserved = $current->diff($editable);
        $after = collect($selected)->merge($preserved)->unique()->sort()->values();
        $metadata = $this->isSystemRole($role)
            ? null
            : $this->validatedMetadata($request, $role);
        $payload = [
            'role_id' => $role->id,
            'before' => $current->all(),
            'after' => $after->all(),
            'before_metadata' => $this->roleMetadata($role),
            'metadata' => $metadata,
            'expires_at' => now()->addMinutes(15)->timestamp,
        ];

        return $this->formView($role, [
            'added' => $after->diff($current)->values()->all(),
            'removed' => $current->diff($after)->values()->all(),
            'metadata_changed' => $metadata !== null
                && $this->roleMetadata($role) !== $this->roleMetadata($role, $metadata),
            'affected_users' => $role->users()->count(),
            'token' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
            'metadata' => $metadata,
        ], $after->intersect($editable)->all());
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->ensureProtectedActor($request);
        $this->ensureWebRole($role);
        abort_if($role->name === 'super-admin', 403, 'Rolul protejat nu poate fi modificat.');
        $payload = $this->validatedToken($request, $role);

        DB::transaction(function () use ($payload, $request, $role): void {
            $lockedRole = Role::query()->lockForUpdate()->findOrFail($role->id);
            $current = $lockedRole->permissions()->pluck('name')->sort()->values()->all();
            if ($current !== $payload['before']) {
                throw ValidationException::withMessages([
                    'confirmation' => 'Drepturile rolului s-au schimbat între timp. Reia previzualizarea.',
                ]);
            }

            $beforeMetadata = $this->roleMetadata($lockedRole);
            if ($beforeMetadata !== $payload['before_metadata']) {
                throw ValidationException::withMessages([
                    'confirmation' => 'Descrierea rolului s-a schimbat între timp. Reia previzualizarea.',
                ]);
            }
            $lockedRole->syncPermissions($payload['after']);
            if (! $this->isSystemRole($lockedRole) && is_array($payload['metadata'])) {
                $profile = AccessRoleProfile::query()->firstOrNew(['role_id' => $lockedRole->id]);
                $profile->fill(Arr::only($payload['metadata'], [
                    'label', 'description', 'workspace', 'requires_locations',
                ]));
                $profile->created_by ??= $request->user()->id;
                $profile->updated_by = $request->user()->id;
                $profile->save();
            }

            activity('access')
                ->performedOn($lockedRole)
                ->causedBy($request->user())
                ->withProperties([
                    'before' => ['permissions' => $payload['before'], 'metadata' => $beforeMetadata],
                    'after' => ['permissions' => $payload['after'], 'metadata' => $this->roleMetadata($lockedRole, $payload['metadata'])],
                ])
                ->log('Drepturi rol actualizate');
        }, 3);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('access.roles.index')->with('status', 'Rolul și drepturile sale au fost actualizate.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->ensureProtectedActor($request);
        $this->ensureWebRole($role);
        abort_if($this->isSystemRole($role), 403, 'Rolurile standard nu pot fi șterse.');

        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'Rolul este încă atribuit unor utilizatori și nu poate fi șters.']);
        }

        DB::transaction(function () use ($request, $role): void {
            activity('access')
                ->performedOn($role)
                ->causedBy($request->user())
                ->withProperties(['role' => $role->name, 'metadata' => $this->roleMetadata($role)])
                ->log('Rol personalizat șters');
            $role->delete();
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('access.roles.index')->with('status', 'Rolul personalizat a fost șters.');
    }

    private function formView(Role $role, ?array $preview = null, ?array $selected = null): View
    {
        $profile = AccessRoleProfile::query()->where('role_id', $role->id)->first();
        $current = $role->permissions->pluck('name');

        return view('access.roles.form', [
            'role' => $role,
            'profile' => $profile,
            'permissionsByModule' => $this->catalog->permissionsByModule(),
            'selectedPermissions' => $selected ?? $current->intersect(array_keys($this->editablePermissions()))->all(),
            'reservedPermissions' => $current->diff(array_keys($this->editablePermissions()))->values()->all(),
            'preview' => $preview,
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    private function editablePermissions(): array
    {
        $reserved = $this->catalog->reservedPermissions();

        return array_filter(
            $this->catalog->seedablePermissions(),
            fn (array $definition, string $ability): bool => ! in_array($ability, $reserved, true),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /** @return array<int, string> */
    private function validatedPermissions(Request $request): array
    {
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(array_keys($this->editablePermissions()))],
        ]);

        return collect($data['permissions'] ?? [])->sort()->values()->all();
    }

    /** @return array{name: string, label: string, description: string, workspace: string, requires_locations: bool} */
    private function validatedMetadata(Request $request, ?Role $role = null): array
    {
        $request->merge(['name' => Str::lower(trim((string) $request->input('name')))]);
        $nameRules = ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'];
        $nameRules[] = $role
            ? Rule::in([$role->name])
            : Rule::unique('roles', 'name');

        $data = $request->validate([
            'name' => $nameRules,
            'label' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:1000'],
            'workspace' => ['required', 'string', 'max:120'],
            'requires_locations' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => $data['name'],
            'label' => trim($data['label']),
            'description' => trim($data['description']),
            'workspace' => trim($data['workspace']),
            'requires_locations' => $request->boolean('requires_locations'),
        ];
    }

    /** @return array<string, mixed> */
    private function validatedToken(Request $request, Role $role): array
    {
        $request->validate(['confirmation_token' => ['required', 'string']]);

        try {
            $payload = json_decode(Crypt::decryptString($request->string('confirmation_token')->toString()), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw ValidationException::withMessages(['confirmation' => 'Previzualizarea nu mai este validă. Reia verificarea.']);
        }

        if (! is_array($payload)
            || (int) ($payload['role_id'] ?? 0) !== $role->id
            || (int) ($payload['expires_at'] ?? 0) < now()->timestamp
            || ! is_array($payload['before'] ?? null)
            || ! is_array($payload['after'] ?? null)
            || ! is_array($payload['before_metadata'] ?? null)) {
            throw ValidationException::withMessages(['confirmation' => 'Previzualizarea a expirat sau nu corespunde rolului.']);
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function roleMetadata(Role $role, ?array $replacement = null): array
    {
        if ($this->isSystemRole($role)) {
            return config("access.roles.{$role->name}", []);
        }

        if ($replacement) {
            return Arr::only($replacement, ['label', 'description', 'workspace', 'requires_locations']);
        }

        return AccessRoleProfile::query()->where('role_id', $role->id)->first()?->only([
            'label', 'description', 'workspace', 'requires_locations',
        ]) ?? [];
    }

    private function isSystemRole(Role $role): bool
    {
        return array_key_exists($role->name, $this->catalog->roles());
    }

    private function ensureProtectedActor(Request $request): void
    {
        abort_unless($request->user()->isProtectedAdministrator(), 403);
    }

    private function ensureWebRole(Role $role): void
    {
        abort_unless($role->guard_name === 'web', 404);
    }
}
