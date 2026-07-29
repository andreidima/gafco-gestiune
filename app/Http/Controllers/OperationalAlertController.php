<?php

namespace App\Http\Controllers;

use App\Models\OperationalAlert;
use App\Services\LocationAccessService;
use App\Services\OperationalAlertAccessService;
use App\Services\OperationalAlertSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class OperationalAlertController extends Controller
{
    public function __construct(
        private readonly OperationalAlertAccessService $access,
        private readonly OperationalAlertSyncService $sync,
        private readonly LocationAccessService $locationAccess,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->access->canUse($user), 403);

        try {
            $this->sync->sync(force: true);
        } catch (Throwable $exception) {
            report($exception);
        }

        $status = in_array($request->input('status'), ['active', 'resolved', 'all'], true)
            ? $request->input('status')
            : 'active';
        $alertType = array_key_exists((string) $request->input('alert_type'), OperationalAlert::TYPE_LABELS)
            ? (string) $request->input('alert_type')
            : null;
        $severity = in_array($request->input('severity'), array_keys(OperationalAlert::SEVERITY_LABELS), true)
            ? (string) $request->input('severity')
            : null;
        $locationId = $request->integer('location_id');
        if ($locationId && ! $this->locationAccess->canView($user, $locationId)) {
            abort(403);
        }

        $baseQuery = $this->access->visibleAlerts($user);
        $query = (clone $baseQuery)
            ->with('location')
            ->when($status === 'active', fn (Builder $alerts) => $alerts->active())
            ->when($status === 'resolved', fn (Builder $alerts) => $alerts->resolved())
            ->when($alertType, fn (Builder $alerts, string $type) => $alerts->where('alert_type', $type))
            ->when($severity, fn (Builder $alerts, string $value) => $alerts->where('severity', $value))
            ->when($locationId, fn (Builder $alerts, int $id) => $alerts->where('location_id', $id))
            ->when(trim((string) $request->input('search')), function (Builder $alerts, string $search): void {
                $alerts->where(function (Builder $matching) use ($search): void {
                    $matching->where('title', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            })
            ->orderByRaw('CASE WHEN resolved_at IS NULL THEN 0 ELSE 1 END')
            ->orderByRaw("CASE severity WHEN 'danger' THEN 0 ELSE 1 END")
            ->orderBy('due_at')
            ->latest('last_detected_at');

        return view('alerts.index', [
            'alerts' => $query->paginate(25)->withQueryString(),
            'activeCount' => (clone $baseQuery)->active()->count(),
            'criticalCount' => (clone $baseQuery)->active()->where('severity', 'danger')->count(),
            'locations' => $this->locationAccess->visibleLocations($user)
                ->orderBy('type')
                ->orderBy('name')
                ->get(),
            'filters' => [
                'status' => $status,
                'alert_type' => $alertType,
                'severity' => $severity,
                'location_id' => $locationId,
                'search' => trim((string) $request->input('search')),
            ],
            'typeLabels' => OperationalAlert::TYPE_LABELS,
            'severityLabels' => OperationalAlert::SEVERITY_LABELS,
            'canConfigure' => $user->hasAnyRole(['super-admin', 'admin']),
        ]);
    }
}
