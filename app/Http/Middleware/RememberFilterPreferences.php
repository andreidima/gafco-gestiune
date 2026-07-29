<?php

namespace App\Http\Middleware;

use App\Models\UserPreference;
use App\Services\LocationAccessService;
use App\Support\ImpersonationContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class RememberFilterPreferences
{
    public function __construct(
        private readonly ImpersonationContext $impersonation,
        private readonly LocationAccessService $locationAccess,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || ! $request->user()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        $routes = config('filter-preferences.routes', []);
        $fields = $routeName && is_array($routes) ? ($routes[$routeName] ?? null) : null;

        if (! is_array($fields) || $fields === [] || ! Schema::hasTable('user_preferences')) {
            return $next($request);
        }

        $preferenceKey = "filters.{$routeName}";
        $preference = UserPreference::query()
            ->where('user_id', $request->user()->id)
            ->where('key', $preferenceKey)
            ->first();

        if ($request->boolean('filters_reset')) {
            if (! $this->impersonation->isActive()) {
                UserPreference::updateOrCreate(
                    ['user_id' => $request->user()->id, 'key' => $preferenceKey],
                    ['value' => []],
                );
            }

            $this->removeControlParameters($request);

            return $next($request);
        }

        if ($request->boolean('filters_submitted')) {
            if (! $this->impersonation->isActive()) {
                UserPreference::updateOrCreate(
                    ['user_id' => $request->user()->id, 'key' => $preferenceKey],
                    ['value' => $this->sanitizeSubmitted($request, $fields)],
                );
            }

            $this->removeControlParameters($request);

            return $next($request);
        }

        $legacy = $preference ? [] : $this->legacyInventoryFilters($request, $routeName);
        $stored = $preference ? (array) $preference->value : $legacy;
        $sanitized = $this->sanitizeStored($request, $stored, $fields);

        if ($preference && $sanitized !== $stored && ! $this->impersonation->isActive()) {
            $preference->update(['value' => $sanitized]);
        } elseif (! $preference && $legacy !== [] && $sanitized !== [] && ! $this->impersonation->isActive()) {
            UserPreference::create([
                'user_id' => $request->user()->id,
                'key' => $preferenceKey,
                'value' => $sanitized,
            ]);
        }

        foreach ($sanitized as $field => $value) {
            if (! $request->query->has($field)) {
                $request->query->set($field, $value);
            }
        }

        return $next($request);
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, bool|int|string>
     */
    private function sanitizeSubmitted(Request $request, array $fields): array
    {
        $values = [];

        foreach ($fields as $field => $definition) {
            if (($definition['type'] ?? null) === 'boolean') {
                if ($request->boolean($field)) {
                    $values[$field] = true;
                }

                continue;
            }

            if (! $request->filled($field)) {
                continue;
            }

            $value = $this->sanitizeValue($request, $request->query($field), $definition);
            if ($value !== null) {
                $values[$field] = $value;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $stored
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, bool|int|string>
     */
    private function sanitizeStored(Request $request, array $stored, array $fields): array
    {
        $values = [];

        foreach ($fields as $field => $definition) {
            if (! array_key_exists($field, $stored)) {
                continue;
            }

            $value = $this->sanitizeValue($request, $stored[$field], $definition);
            if ($value !== null && $value !== false) {
                $values[$field] = $value;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function sanitizeValue(Request $request, mixed $value, array $definition): bool|int|string|null
    {
        return match ($definition['type'] ?? null) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'positive_integer' => filter_var(
                $value,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]],
            ) ?: null,
            'visible_location' => $this->visibleLocationId($request, $value),
            'enum' => in_array((string) $value, $definition['values'] ?? [], true)
                ? (string) $value
                : null,
            default => null,
        };
    }

    private function visibleLocationId(Request $request, mixed $value): ?int
    {
        $locationId = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if (! $locationId || ! $this->locationAccess->canView($request->user(), (int) $locationId)) {
            return null;
        }

        return (int) $locationId;
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyInventoryFilters(Request $request, ?string $routeName): array
    {
        if ($routeName !== 'inventory.index') {
            return [];
        }

        $legacy = UserPreference::query()
            ->where('user_id', $request->user()->id)
            ->where('key', 'inventory.index')
            ->first()?->value ?? [];

        return [
            'location_id' => data_get($legacy, 'filters.location_id'),
            'hide_zero' => data_get($legacy, 'filters.hide_zero', false),
        ];
    }

    private function removeControlParameters(Request $request): void
    {
        $request->query->remove('filters_submitted');
        $request->query->remove('filters_reset');
    }
}
