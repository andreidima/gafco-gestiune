<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\ConsumptionReport;
use App\Models\CustodyTransfer;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\SupplierReception;
use App\Models\Task;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\TransferApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $dashboardMode = match (true) {
            $user->usesDriverWorkspace() => 'driver',
            $user->usesWorkerWorkspace() => 'worker',
            $user->hasGlobalOperationalReadAccess() || $user->hasRole('contabil') => 'operations',
            $user->hasAnyRole(['sef-santier', 'gestionar-baza']) => 'manager',
            default => 'limited',
        };
        $showOperationsOverview = $dashboardMode === 'operations';
        $baseData = [
            'dashboardMode' => $dashboardMode,
            'showOperationsOverview' => $showOperationsOverview,
            'actionQueues' => $this->actionQueues($user, $dashboardMode),
            'ownTasks' => $dashboardMode === 'driver' ? $this->driverTasks($user) : collect(),
            'stats' => [],
            'assetStatusCounts' => collect(),
            'transferStatusCounts' => collect(),
            'consumptionTrend' => collect(),
            'topLocations' => collect(),
            'activityFeed' => collect(),
            'transfers' => collect(),
            'driverRequests' => collect(),
            'stockSnapshot' => collect(),
        ];

        if (! $showOperationsOverview) {
            return view('dashboard', $baseData);
        }

        $assetStatusCounts = TrackedAsset::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $transferStatusCounts = Transfer::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $consumptionReports = ConsumptionReport::with('lines')
            ->where('reported_at', '>=', now()->subDays(30))
            ->get();
        $consumptionTrend = collect(range(29, 0))
            ->map(function (int $daysAgo) use ($consumptionReports) {
                $date = now()->subDays($daysAgo);
                $total = $consumptionReports
                    ->filter(fn (ConsumptionReport $report) => $report->reported_at?->isSameDay($date))
                    ->sum(fn (ConsumptionReport $report) => $report->lines->sum('quantity'));

                return [
                    'label' => $date->format('d.m'),
                    'value' => round((float) $total, 2),
                ];
            });
        $topLocations = Location::where('active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(function (Location $location) {
                $location->assets_count = TrackedAsset::where('current_location_id', $location->id)->count();

                return $location;
            })
            ->sortByDesc('assets_count')
            ->take(6)
            ->values();
        $activityFeed = $this->activityFeed();

        return view('dashboard', array_merge($baseData, [
            'stats' => [
                'Baze' => Location::where('type', 'base')->count(),
                'Santiere active' => Location::where('type', 'site')->where('active', true)->count(),
                'Articole' => CatalogItem::where('active', true)->count(),
                'Asset-uri QR' => TrackedAsset::count(),
                'In tranzit' => Transfer::where('status', 'in_transit')->count(),
                'Cereri sofer' => Task::whereIn('status', ['unassigned', 'pending_acceptance', 'accepted', 'in_progress'])->count(),
                'Alerte' => Task::whereNotNull('manager_deadline')->where('manager_deadline', '<', now())->whereNotIn('status', ['completed', 'cancelled', 'archived'])->count()
                    + TrackedAsset::where('status', 'lost')->count(),
                'Receptii luna' => SupplierReception::where('received_at', '>=', now()->subDays(30))->count(),
            ],
            'assetStatusCounts' => $assetStatusCounts,
            'transferStatusCounts' => $transferStatusCounts,
            'consumptionTrend' => $consumptionTrend,
            'topLocations' => $topLocations,
            'activityFeed' => $activityFeed,
            'transfers' => Transfer::with(['sourceLocation', 'destinationLocation', 'driver'])
                ->latest()
                ->limit(8)
                ->get(),
            'driverRequests' => Task::with(['destinationLocation', 'currentAssignment.driver'])
                ->whereIn('status', ['unassigned', 'pending_acceptance', 'accepted', 'in_progress'])
                ->latest()
                ->limit(6)
                ->get(),
            'stockSnapshot' => StockLevel::with(['location', 'catalogItem'])
                ->where('quantity', '>', 0)
                ->latest()
                ->limit(10)
                ->get(),
        ]));
    }

    private function actionQueues(User $user, string $dashboardMode): array
    {
        if ($dashboardMode === 'limited') {
            return [];
        }

        if ($dashboardMode === 'driver') {
            $tasks = $this->visibleTasks($user);

            return [
                [
                    'title' => 'Asteapta raspunsul meu',
                    'count' => (clone $tasks)->where('status', 'pending_acceptance')
                        ->whereHas('currentAssignment', fn ($assignment) => $assignment->where('driver_id', $user->id)->where('status', 'pending'))
                        ->count(),
                    'description' => 'Accepta sau refuza sarcinile noi.',
                    'href' => route('tasks.index', ['status' => 'pending_acceptance']),
                    'icon' => 'fa-hand-pointer',
                    'tone' => 'accent-amber',
                ],
                [
                    'title' => 'Sarcini intarziate',
                    'count' => (clone $tasks)->whereNotNull('manager_deadline')->where('manager_deadline', '<', now())
                        ->whereNotIn('status', ['completed', 'cancelled', 'archived'])->count(),
                    'description' => 'Termenul oficial a fost depasit.',
                    'href' => route('tasks.index', ['overdue' => 1]),
                    'icon' => 'fa-triangle-exclamation',
                    'tone' => 'accent-danger',
                ],
                [
                    'title' => 'Sarcini active',
                    'count' => (clone $tasks)->whereIn('status', ['accepted', 'in_progress'])->count(),
                    'description' => 'Sarcinile pe care le poti continua.',
                    'href' => route('tasks.index'),
                    'icon' => 'fa-truck-fast',
                    'tone' => 'accent-forest',
                ],
            ];
        }

        if ($dashboardMode === 'worker') {
            $pendingCustody = CustodyTransfer::where('status', 'pending')
                ->where(fn ($query) => $query->where('from_user_id', $user->id)->orWhere('to_user_id', $user->id))
                ->count();

            return [
                ['title' => 'Predari de confirmat', 'count' => $pendingCustody, 'description' => 'Confirma predarea sau primirea echipamentelor.', 'href' => route('field.worker', ['status' => 'pending']), 'icon' => 'fa-handshake', 'tone' => 'accent-amber'],
                ['title' => 'Echipamentele mele', 'count' => TrackedAsset::where('current_custodian_id', $user->id)->count(), 'description' => 'Bunurile aflate acum in custodia ta.', 'href' => route('field.worker'), 'icon' => 'fa-screwdriver-wrench', 'tone' => 'accent-forest'],
                ['title' => 'Scanare QR', 'count' => null, 'description' => 'Deschide rapid un echipament dupa cod.', 'href' => route('qr-scan.index'), 'icon' => 'fa-qrcode', 'tone' => 'accent-slate'],
            ];
        }

        $tasks = $this->visibleTasks($user);
        $canDispatch = $user->can('create', Task::class);
        if ($canDispatch) {
            return [
                [
                    'title' => 'Necesita aprobarea mea',
                    'count' => $this->pendingApprovals($user)->count(),
                    'description' => 'Transferuri la care poti lua acum o decizie.',
                    'href' => route('field.site-manager'),
                    'icon' => 'fa-user-check',
                    'tone' => 'accent-rose',
                ],
                [
                    'title' => 'Sarcini intarziate',
                    'count' => (clone $tasks)->whereNotNull('manager_deadline')->where('manager_deadline', '<', now())
                        ->whereNotIn('status', ['completed', 'cancelled', 'archived'])->count(),
                    'description' => 'Sarcini vizibile cu termen depasit.',
                    'href' => route('tasks.index', ['overdue' => 1]),
                    'icon' => 'fa-triangle-exclamation',
                    'tone' => 'accent-danger',
                ],
                [
                    'title' => 'Sarcini nealocate',
                    'count' => (clone $tasks)->where('status', 'unassigned')->count(),
                    'description' => 'Alege un sofer si trimite spre acceptare.',
                    'href' => route('tasks.dispatch').'#unassigned-tasks',
                    'icon' => 'fa-inbox',
                    'tone' => 'accent-amber',
                ],
                [
                    'title' => 'Soferi disponibili',
                    'count' => User::assignableDrivers()->where('active', true)
                        ->whereDoesntHave('taskAssignments', fn ($assignment) => $assignment
                            ->whereIn('status', ['pending', 'accepted', 'reassignment_requested'])
                            ->whereHas('task', fn ($task) => $task->whereNotIn('status', ['completed', 'cancelled', 'archived'])))
                        ->count(),
                    'description' => 'Liberi acum pentru o sarcina noua.',
                    'href' => route('tasks.dispatch'),
                    'icon' => 'fa-users-viewfinder',
                    'tone' => 'accent-forest',
                ],
            ];
        }

        return [
            ['title' => 'Transferuri in tranzit', 'count' => Transfer::where('status', 'in_transit')->count(), 'description' => 'Fluxuri active in acest moment.', 'href' => route('transfers.index', ['status' => 'in_transit']), 'icon' => 'fa-right-left', 'tone' => 'accent-amber'],
            ['title' => 'Receptii luna', 'count' => SupplierReception::where('received_at', '>=', now()->subDays(30))->count(), 'description' => 'Intrari inregistrate in ultimele 30 de zile.', 'href' => route('supplier-receptions.index'), 'icon' => 'fa-receipt', 'tone' => 'accent-teal'],
        ];
    }

    private function visibleTasks(User $user): Builder
    {
        $query = Task::query();
        if ($user->hasGlobalOperationalReadAccess()) {
            return $query;
        }
        if ($user->usesDriverWorkspace()) {
            return $query->where(function ($visible) use ($user): void {
                $visible->whereHas('currentAssignment', fn ($assignment) => $assignment->where('driver_id', $user->id))
                    ->orWhereHas('assignments', fn ($assignment) => $assignment
                        ->where('driver_id', $user->id)
                        ->whereIn('status', ['accepted', 'reassignment_requested'])
                        ->whereHas('replacementCandidates', fn ($candidate) => $candidate->where('status', 'pending')));
            });
        }
        $locationIds = $user->activeManagedLocations()->pluck('locations.id');

        return $query->where(function ($visible) use ($user, $locationIds): void {
            $visible->where('created_by', $user->id)
                ->orWhereIn('source_location_id', $locationIds)
                ->orWhereIn('destination_location_id', $locationIds);
        });
    }

    private function pendingApprovals(User $user): Builder
    {
        $query = TransferApproval::query()
            ->where('status', 'pending')
            ->whereHas('transfer', fn ($transfer) => $transfer
                ->whereColumn('transfers.revision', 'transfer_approvals.revision')
                ->whereNotIn('status', ['received', 'cancelled']));

        if ($user->isOperationsAdmin()) {
            return $query->where('scope', '!=', 'driver');
        }

        $locationIds = $user->activeManagedLocations()->pluck('locations.id');

        return $query->where(function ($eligible) use ($user, $locationIds): void {
            $eligible->where('expected_user_id', $user->id)->orWhereIn('location_id', $locationIds);
        });
    }

    private function driverTasks(User $user): Collection
    {
        $tasks = $this->visibleTasks($user)
            ->with([
                'sourceLocation', 'destinationLocation', 'currentAssignment',
                'assignments' => fn ($assignments) => $assignments
                    ->where('driver_id', $user->id)
                    ->whereIn('status', ['pending', 'accepted', 'reassignment_requested'])
                    ->latest(),
            ])
            ->whereNotIn('status', ['completed', 'cancelled', 'archived'])
            ->orderByRaw('CASE WHEN manager_deadline IS NOT NULL AND manager_deadline < ? THEN 0 WHEN status = ? THEN 1 ELSE 2 END', [now(), 'pending_acceptance'])
            ->orderBy('manager_deadline')
            ->limit(6)
            ->get();

        $tasks->each(function (Task $task) use ($user): void {
            $assignment = $task->assignments
                ->where('driver_id', $user->id)
                ->whereIn('status', ['pending', 'accepted', 'reassignment_requested'])
                ->sortByDesc('id')
                ->first();
            if ($assignment) {
                $task->setRelation('currentAssignment', $assignment);
            }
        });

        return $tasks;
    }

    private function activityFeed(): Collection
    {
        $transfers = Transfer::with(['sourceLocation', 'destinationLocation'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Transfer $transfer) => [
                'icon' => 'fa-right-left',
                'type' => 'Transfer',
                'title' => $transfer->number,
                'description' => ($transfer->sourceLocation?->code ?? '-').' -> '.($transfer->destinationLocation?->code ?? '-'),
                'date' => $transfer->created_at,
                'status' => $transfer->status,
            ]);

        $receptions = SupplierReception::with('location')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (SupplierReception $reception) => [
                'icon' => 'fa-receipt',
                'type' => 'Receptie',
                'title' => $reception->number,
                'description' => $reception->location?->code ?? '-',
                'date' => $reception->received_at ?? $reception->created_at,
                'status' => $reception->status,
            ]);

        $custody = CustodyTransfer::with(['trackedAsset', 'toUser'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (CustodyTransfer $transfer) => [
                'icon' => 'fa-qrcode',
                'type' => 'Custodie',
                'title' => $transfer->trackedAsset?->asset_code ?? $transfer->qr_token,
                'description' => $transfer->toUser?->name ?? '-',
                'date' => $transfer->accepted_at ?? $transfer->created_at,
                'status' => $transfer->status,
            ]);

        return $transfers
            ->merge($receptions)
            ->merge($custody)
            ->sortByDesc('date')
            ->take(10)
            ->values();
    }
}
