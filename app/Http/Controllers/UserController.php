<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccessCatalog;
use App\Services\EffectiveAccessService;
use App\Services\LocationResponsibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        private readonly AccessCatalog $accessCatalog,
        private readonly EffectiveAccessService $effectiveAccess,
        private readonly LocationResponsibilityService $locationResponsibilities,
    ) {}

    public function index(Request $request): View
    {
        $canSeeProtectedAccounts = $request->user()->isProtectedAdministrator();
        $users = User::query()
            ->with(['roles', 'activeManagedLocations']);
        $totalUsers = User::query();

        if (! $canSeeProtectedAccounts) {
            $this->excludeProtectedAccounts($users);
            $this->excludeProtectedAccounts($totalUsers);
        }

        return view('users.index', [
            'users' => $users
                ->when($request->search, fn ($query, $search) => $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhereRaw('UPPER(login_code) LIKE ?', ['%'.Str::upper($search).'%'])
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                }))
                ->when($request->role, fn ($query, $role) => $query->role($role))
                ->when($request->filled('active'), fn ($query) => $query->where('active', $request->boolean('active')))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'roles' => $this->assignableRoles(),
            'roleLabels' => $this->roleLabels(),
            'rolesRequiringLocations' => $this->accessCatalog->rolesRequiringLocations(),
            'totalUsers' => $totalUsers->count(),
        ]);
    }

    public function create(): View
    {
        return view('users.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($data, $request): void {
            $user = User::create([
                'name' => $data['name'],
                'login_code' => strtoupper(trim($data['login_code'])),
                'email' => ($data['email'] ?? null) ?: strtolower(trim($data['login_code'])).'@login.invalid',
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'active' => $request->boolean('active', true),
                'email_verified_at' => now(),
            ]);
            $user->syncRoles($data['roles'] ?? []);

            activity('access')
                ->performedOn($user)
                ->causedBy($request->user())
                ->withProperties(['after' => $this->accessSnapshot($user)])
                ->log('Acces utilizator creat');
        });

        return redirect()->route('users.index')->with('status', 'Utilizatorul a fost creat.');
    }

    public function edit(User $user): View
    {
        $this->ensureCanManage($user);
        $user->load('roles');

        return view('users.form', $this->formData($user));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureCanManage($user);
        $data = $this->validatedData($request, $user);
        if ($user->is($request->user()) && ! $request->boolean('active')) {
            throw ValidationException::withMessages(['active' => 'Nu iti poti dezactiva propriul cont.']);
        }

        $updates = [
            'name' => $data['name'],
            'login_code' => strtoupper(trim($data['login_code'])),
            'email' => ($data['email'] ?? null) ?: strtolower(trim($data['login_code'])).'@login.invalid',
            'phone' => $data['phone'] ?? null,
            'active' => $request->boolean('active'),
        ];
        if (! empty($data['password'])) {
            $updates['password'] = Hash::make($data['password']);
        }

        if ($user->isProtectedAdministrator()) {
            $updates['email'] = $user->email;
        }

        DB::transaction(function () use ($data, $request, $updates, $user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $before = $this->accessSnapshot($lockedUser);

            $lockedUser->update($updates);
            $roles = $data['roles'] ?? [];
            if ($lockedUser->isProtectedAdministrator()) {
                $roles[] = 'super-admin';
            }
            $lockedUser->syncRoles(array_values(array_unique($roles)));
            $removedLocations = $this->locationResponsibilities->reconcile($lockedUser->fresh());
            $after = $this->accessSnapshot($lockedUser->fresh());

            if ($before !== $after || $removedLocations !== []) {
                activity('access')
                    ->performedOn($lockedUser)
                    ->causedBy($request->user())
                    ->withProperties([
                        'before' => $before,
                        'after' => $after,
                        'removed_location_responsibilities' => $removedLocations,
                    ])
                    ->log('Acces utilizator actualizat');
            }
        }, 3);

        return redirect()->route('users.index')->with('status', 'Utilizatorul a fost actualizat.');
    }

    private function formData(?User $user = null): array
    {
        return [
            'user' => $user,
            'roles' => $this->assignableRoles(),
            'roleLabels' => $this->roleLabels(),
            'accessWarnings' => $user ? $this->effectiveAccess->warnings($user) : collect(),
        ];
    }

    private function validatedData(Request $request, ?User $user = null): array
    {
        $request->merge([
            'login_code' => Str::upper(trim((string) $request->input('login_code'))),
            'email' => $user?->isProtectedAdministrator()
                ? $user->email
                : Str::lower(trim((string) $request->input('email'))),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'login_code' => ['required', 'string', 'max:40', Rule::unique('users', 'login_code')->ignore($user)],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::notIn([
                    $user?->isProtectedAdministrator()
                        ? '__protected-account-email-is-locked__'
                        : $this->protectedAdministratorEmail(),
                ]),
                Rule::unique('users', 'email')->ignore($user),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:6'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::notIn(['super-admin']), 'exists:roles,name'],
            'active' => ['nullable', 'boolean'],
        ]);
    }

    private function assignableRoles(): Collection
    {
        return Role::query()
            ->where('name', '!=', 'super-admin')
            ->orderBy('name')
            ->get();
    }

    /** @return array<string, string> */
    private function roleLabels(): array
    {
        return $this->assignableRoles()
            ->mapWithKeys(fn (Role $role): array => [$role->name => $this->accessCatalog->roleLabel($role->name)])
            ->all();
    }

    private function ensureCanManage(User $user): void
    {
        if ($user->isProtectedAdministrator() || $user->hasRole('super-admin')) {
            abort_unless(request()->user()->isProtectedAdministrator(), 403);
        }
    }

    private function excludeProtectedAccounts(Builder $query): void
    {
        $query
            ->whereRaw('LOWER(TRIM(email)) != ?', [$this->protectedAdministratorEmail()])
            ->whereDoesntHave('roles', fn ($roles) => $roles->where('name', 'super-admin'));
    }

    private function protectedAdministratorEmail(): string
    {
        return Str::lower(trim((string) config('roles.protected_admin_email')));
    }

    /** @return array{active: bool, roles: array<int, string>, direct_permissions: array<int, string>, managed_locations: array<int, string>} */
    private function accessSnapshot(User $user): array
    {
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');
        $user->unsetRelation('activeManagedLocations');
        $user->load(['roles', 'permissions', 'activeManagedLocations']);

        return [
            'active' => (bool) $user->active,
            'roles' => $user->roles->pluck('name')->sort()->values()->all(),
            'direct_permissions' => $user->permissions->pluck('name')->sort()->values()->all(),
            'managed_locations' => $user->activeManagedLocations
                ->map(fn ($location): string => "{$location->code} - {$location->name}")
                ->sort()
                ->values()
                ->all(),
        ];
    }
}
