<?php

namespace App\Http\Controllers;

use App\Models\AccessRoleProfile;
use App\Models\User;
use App\Services\AccessCatalog;
use App\Services\EffectiveAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class AccessAdministrationController extends Controller
{
    public function __construct(
        private readonly AccessCatalog $catalog,
        private readonly EffectiveAccessService $effectiveAccess,
    ) {}

    public function index(Request $request): View
    {
        $baseQuery = $this->visibleUsers($request->user());
        $usersQuery = (clone $baseQuery)->with(['roles.permissions', 'permissions', 'activeManagedLocations']);
        $roleRecords = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->get();
        $profiles = AccessRoleProfile::query()->whereIn('role_id', $roleRecords->pluck('id'))->get()->keyBy('role_id');
        $roleMetadata = $roleRecords->mapWithKeys(function (Role $role) use ($profiles): array {
            $system = config("access.roles.{$role->name}");
            $profile = $profiles->get($role->id);

            return [$role->name => $system ?? [
                'description' => $profile?->description ?? 'Rol personalizat fără descriere.',
                'workspace' => $profile?->workspace ?? 'Personalizat',
                'system' => false,
                'privileged' => false,
                'requires_locations' => (bool) $profile?->requires_locations,
            ]];
        })->all();

        $users = $usersQuery
            ->when($request->search, fn (Builder $query, string $search) => $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery->where('name', 'like', "%{$search}%")
                    ->orWhereRaw('UPPER(login_code) LIKE ?', ['%'.Str::upper($search).'%'])
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->when($request->role, fn (Builder $query, string $role) => $query->role($role))
            ->when($request->filled('active'), fn (Builder $query) => $query->where('active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $users->getCollection()->each(function (User $user): void {
            $user->setAttribute('access_summary', $this->effectiveAccess->summary($user));
            $user->setAttribute('access_warnings', $this->effectiveAccess->warnings($user));
        });

        return view('access.index', [
            'users' => $users,
            'roles' => $roleMetadata,
            'roleRecords' => $roleRecords,
            'roleLabels' => $roleRecords->mapWithKeys(fn (Role $role): array => [$role->name => $this->catalog->roleLabel($role->name)])->all(),
            'permissionsByModule' => $this->catalog->permissionsByModule(),
            'catalog' => $this->catalog,
            'canManageAccess' => $request->user()->isProtectedAdministrator(),
            'stats' => [
                'total' => (clone $baseQuery)->count(),
                'active' => (clone $baseQuery)->where('active', true)->count(),
                'without_role' => (clone $baseQuery)->whereDoesntHave('roles')->count(),
                'with_direct_permissions' => (clone $baseQuery)->whereHas('permissions')->count(),
                'missing_location_scope' => (clone $baseQuery)
                    ->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', $this->catalog->rolesRequiringLocations()))
                    ->whereDoesntHave('activeManagedLocations')
                    ->count(),
            ],
        ]);
    }

    public function show(Request $request, User $user): View
    {
        $this->ensureVisible($request->user(), $user);
        $user->load(['roles.permissions', 'permissions', 'permissionExceptions.permission', 'permissionExceptions.granter', 'activeManagedLocations']);

        return view('access.show', [
            'user' => $user,
            'summary' => $this->effectiveAccess->summary($user),
            'warnings' => $this->effectiveAccess->warnings($user),
            'decisionsByModule' => $this->effectiveAccess->groupedDecisions($user),
            'exceptionContexts' => $user->permissionExceptions->keyBy('permission.name'),
            'roleLabels' => $user->roles
                ->mapWithKeys(fn (Role $role): array => [$role->name => $this->catalog->roleLabel($role->name)])
                ->all(),
            'canManageExceptions' => $request->user()->isProtectedAdministrator()
                && ! $user->isProtectedAdministrator()
                && ! $user->hasRole('super-admin'),
            'recentActivities' => Activity::query()
                ->where('subject_type', $user->getMorphClass())
                ->where('subject_id', $user->getKey())
                ->where('log_name', 'access')
                ->with('causer')
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    private function visibleUsers(User $actor): Builder
    {
        $query = User::query();
        if (! $actor->isProtectedAdministrator()) {
            $query
                ->whereRaw('LOWER(TRIM(email)) != ?', [Str::lower(trim((string) config('roles.protected_admin_email')))])
                ->whereDoesntHave('roles', fn (Builder $roles) => $roles->where('name', 'super-admin'));
        }

        return $query;
    }

    private function ensureVisible(User $actor, User $target): void
    {
        if (($target->isProtectedAdministrator() || $target->hasRole('super-admin'))
            && ! $actor->isProtectedAdministrator()) {
            abort(403);
        }
    }
}
