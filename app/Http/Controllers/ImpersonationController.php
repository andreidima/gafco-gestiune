<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ImpersonationService;
use App\Support\ImpersonationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ImpersonationController extends Controller
{
    public function users(
        Request $request,
        ImpersonationContext $context,
        ImpersonationService $impersonation,
    ): JsonResponse {
        $actor = $context->actor();
        abort_unless($actor?->active && $actor->can(ImpersonationContext::PERMISSION), 403);

        $search = Str::of((string) $request->query('search'))->trim()->limit(100)->toString();
        $role = Str::of((string) $request->query('role'))->trim()->limit(100)->toString();
        $currentTargetId = $context->isActive() ? $request->user()?->getKey() : null;
        $recentTargetIds = $context->recentTargetIds();
        $roleLabels = config('roles.labels', []);

        $users = User::query()
            ->with(['roles.permissions', 'permissions'])
            ->where('active', true)
            ->whereKeyNot($actor->getKey())
            ->when($currentTargetId, fn ($query) => $query->whereKeyNot($currentTargetId))
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search): void {
                $searchQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('login_code', 'like', "%{$search}%");
            }))
            ->when($role !== '', fn ($query) => $query->role($role))
            ->orderBy('name')
            ->limit(60)
            ->get()
            ->filter(fn (User $user): bool => $impersonation->canTake($actor, $user))
            ->sortBy(function (User $user) use ($recentTargetIds): string {
                $recentPosition = array_search($user->getKey(), $recentTargetIds, true);

                return sprintf(
                    '%02d-%s',
                    $recentPosition === false ? 99 : $recentPosition,
                    Str::lower($user->name),
                );
            })
            ->take(30)
            ->values()
            ->map(fn (User $user): array => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'login_code' => $user->login_code,
                'roles' => $user->roles
                    ->map(fn ($userRole): string => $roleLabels[$userRole->name] ?? $userRole->name)
                    ->values()
                    ->all(),
                'recent' => in_array($user->getKey(), $recentTargetIds, true),
                'take_url' => route('impersonation.take', $user),
            ]);

        return response()->json(['users' => $users]);
    }

    public function take(
        Request $request,
        User $user,
        ImpersonationService $impersonation,
    ): RedirectResponse {
        $impersonation->take($request, $user);

        return redirect()->route('dashboard')->with(
            'status',
            "Ai intrat în contul utilizatorului {$user->name}.",
        );
    }

    public function stop(
        Request $request,
        ImpersonationService $impersonation,
    ): RedirectResponse {
        $actor = $impersonation->stop($request);

        return $actor
            ? redirect()->route('dashboard')->with('status', 'Ai revenit la contul tău.')
            : redirect()->route('login');
    }
}
