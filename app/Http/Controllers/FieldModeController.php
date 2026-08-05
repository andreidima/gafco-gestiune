<?php

namespace App\Http\Controllers;

use App\Models\ConsumptionReport;
use App\Models\CustodyTransfer;
use App\Models\Location;
use App\Models\MaterialCustody;
use App\Models\StockLevel;
use App\Models\Task;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\TransferApproval;
use App\Models\User;
use App\Services\CustodyWorkflowService;
use App\Services\LocationAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class FieldModeController extends Controller
{
    public function __construct(
        private readonly LocationAccessService $locationAccess,
        private readonly CustodyWorkflowService $custodyWorkflow,
    ) {}

    public function driver(): RedirectResponse
    {
        return redirect()->route('tasks.index');
    }

    public function siteManager(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->hasAbility('transfers.approve') || $user->hasAbility('consumption-reports.create'), 403);

        $managedLocationIds = $user->activeManagedLocations()->pluck('locations.id');
        $canViewTransfers = $user->hasAbility('transfers.view');
        $canApproveTransfers = $user->hasAbility('transfers.approve');
        $canViewTasks = $user->hasAbility('tasks.view');
        $canViewConsumption = $user->hasAbility('consumption-reports.view');
        $globalTransfers = $user->hasGlobalAbility('transfers.view');
        $globalApprovals = $user->hasGlobalAbility('transfers.approve');
        $globalTasks = $user->hasGlobalAbility('tasks.view');
        $globalConsumption = $user->hasGlobalAbility('consumption-reports.view');
        $visibleTransfers = Transfer::query()
            ->whereIn('status', ['pending_approval', 'approved', 'in_transit'])
            ->when(! $canViewTransfers, fn ($query) => $query->whereRaw('1 = 0'))
            ->when(! $globalTransfers, fn ($query) => $query->where(function ($visible) use ($managedLocationIds): void {
                $visible->whereIn('source_location_id', $managedLocationIds)->orWhereIn('destination_location_id', $managedLocationIds);
            }));
        $activeTransfersCount = (clone $visibleTransfers)->count();
        $pendingTransfers = $visibleTransfers
            ->with([
                'sourceLocation', 'destinationLocation', 'driver', 'lines.catalogItem', 'lines.trackedAsset',
                'task.currentAssignment.driver', 'approvals.expectedUser', 'approvals.location',
            ])
            ->when($request->transfer_search, fn ($query, $search) => $query->where(function ($filtered) use ($search): void {
                $filtered->where('number', 'like', "%{$search}%")->orWhere('document_number', 'like', "%{$search}%");
            }))
            ->when($request->transfer_status, fn ($query, $status) => $query->where('status', $status))
            ->orderByRaw("CASE status WHEN 'pending_approval' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
            ->latest()
            ->limit(25)
            ->get();

        $pendingApprovalsQuery = TransferApproval::query()
            ->where('status', 'pending')
            ->when(! $canApproveTransfers, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($globalApprovals, fn ($query) => $query->where('scope', '!=', 'driver'))
            ->whereHas('transfer', fn ($transfer) => $transfer
                ->whereColumn('transfers.revision', 'transfer_approvals.revision')
                ->whereNotIn('status', ['received', 'cancelled']))
            ->when(! $globalApprovals, fn ($query) => $query->whereIn('location_id', $managedLocationIds));
        $pendingApprovalsCount = (clone $pendingApprovalsQuery)->count();
        $pendingApprovals = $pendingApprovalsQuery
            ->with(['transfer.sourceLocation', 'transfer.destinationLocation', 'location'])
            ->latest()
            ->limit(12)
            ->get();

        $visibleTasks = Task::query()
            ->when(! $canViewTasks, fn ($query) => $query->whereRaw('1 = 0'))
            ->when(! $globalTasks, fn ($query) => $query->where(function ($visible) use ($user, $managedLocationIds): void {
                $visible->where('created_by', $user->id)
                    ->orWhereIn('source_location_id', $managedLocationIds)
                    ->orWhereIn('destination_location_id', $managedLocationIds);
            }));
        $overdueTasksCount = (clone $visibleTasks)
            ->whereNotNull('manager_deadline')
            ->where('manager_deadline', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled', 'archived'])
            ->count();

        $recentConsumptionQuery = ConsumptionReport::with(['location', 'lines.catalogItem'])
            ->when(! $canViewConsumption, fn ($query) => $query->whereRaw('1 = 0'))
            ->when(! $globalConsumption, fn ($query) => $query->whereIn('location_id', $managedLocationIds));

        return view('field.site-manager', [
            'pendingTransfers' => $pendingTransfers,
            'pendingApprovals' => $pendingApprovals,
            'pendingApprovalsCount' => $pendingApprovalsCount,
            'activeTransfersCount' => $activeTransfersCount,
            'overdueTasksCount' => $overdueTasksCount,
            'consumptionThisMonthCount' => (clone $recentConsumptionQuery)->where('reported_at', '>=', now()->subDays(30))->count(),
            'managedLocationsCount' => $user->hasAbility('locations.view')
                ? ($user->hasGlobalAbility('locations.view') ? Location::where('active', true)->count() : $managedLocationIds->count())
                : 0,
            'recentConsumption' => $recentConsumptionQuery
                ->latest('reported_at')
                ->limit(8)
                ->get(),
        ]);
    }

    public function worker(Request $request): View
    {
        $user = $request->user();
        $expandedCustodyAvailable = Schema::hasTable('material_custodies')
            && Schema::hasColumn('custody_transfers', 'operation_type');
        $globalView = $user->hasGlobalAbility('custody.view');
        $managedLocationIds = $user->activeManagedLocations()
            ->where('locations.active', true)
            ->pluck('locations.id')
            ->map(fn ($id) => (int) $id);
        $writeLocations = $user->hasGlobalAbility('custody.initiate')
            ? Location::where('active', true)->orderBy('name')->get()
            : $user->activeManagedLocations()->where('locations.active', true)->orderBy('name')->get();
        CustodyTransfer::where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        $assets = TrackedAsset::with(['catalogItem', 'currentLocation', 'currentCustodian'])
            ->whereNotNull('current_custodian_id')
            ->whereIn('status', ['available', 'in_use'])
            ->when(! $globalView && $managedLocationIds->isNotEmpty(), fn ($query) => $query->where(function ($visible) use ($user, $managedLocationIds): void {
                $visible->where('current_custodian_id', $user->id)
                    ->orWhereIn('current_location_id', $managedLocationIds);
            }))
            ->when(! $globalView && $managedLocationIds->isEmpty(), fn ($query) => $query->where('current_custodian_id', $user->id))
            ->orderBy('catalog_item_id')
            ->orderBy('asset_code')
            ->limit(80)
            ->get();

        $custodyQuery = CustodyTransfer::with([
            'trackedAsset.catalogItem', 'catalogItem', 'fromUser', 'toUser',
            'location', 'initiator', 'managerApprover',
        ])
            ->when(! $globalView && $managedLocationIds->isNotEmpty(), fn ($query) => $query->where(function ($visible) use ($user, $managedLocationIds): void {
                $visible->where('from_user_id', $user->id)
                    ->orWhere('to_user_id', $user->id)
                    ->orWhereIn('location_id', $managedLocationIds);
            }))
            ->when(! $globalView && $managedLocationIds->isEmpty(), fn ($query) => $query->where(function ($visible) use ($user): void {
                $visible->where('from_user_id', $user->id)->orWhere('to_user_id', $user->id);
            }));
        $pendingDecisions = (clone $custodyQuery)
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->filter(fn (CustodyTransfer $transfer) => $this->custodyWorkflow->canDecide($transfer, $user))
            ->values();
        $custodyTransfers = (clone $custodyQuery)
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->search, fn ($query, $search) => $query->where(function ($filtered) use ($search): void {
                $filtered->where('qr_token', 'like', "%{$search}%")
                    ->orWhereHas('trackedAsset', fn ($asset) => $asset->where('asset_code', 'like', "%{$search}%"))
                    ->orWhereHas('catalogItem', fn ($item) => $item->where('name', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $materialCustodies = collect();
        $issuableMaterials = collect();
        if ($expandedCustodyAvailable) {
            $materialCustodies = MaterialCustody::with(['catalogItem', 'location', 'user'])
                ->where('quantity', '>', 0)
                ->when(! $globalView && $managedLocationIds->isNotEmpty(), fn ($query) => $query->where(function ($visible) use ($user, $managedLocationIds): void {
                    $visible->where('user_id', $user->id)->orWhereIn('location_id', $managedLocationIds);
                }))
                ->when(! $globalView && $managedLocationIds->isEmpty(), fn ($query) => $query->where('user_id', $user->id))
                ->orderBy('catalog_item_id')
                ->get();

            if ($writeLocations->isNotEmpty()) {
                $heldTotals = MaterialCustody::query()
                    ->selectRaw('location_id, catalog_item_id, SUM(quantity) as quantity')
                    ->whereIn('location_id', $writeLocations->pluck('id'))
                    ->groupBy('location_id', 'catalog_item_id')
                    ->get()
                    ->keyBy(fn ($row) => $row->location_id.'-'.$row->catalog_item_id);
                $pendingTotals = CustodyTransfer::query()
                    ->selectRaw('location_id, catalog_item_id, SUM(quantity) as quantity')
                    ->where('operation_type', 'issue')
                    ->where('status', 'pending')
                    ->whereIn('location_id', $writeLocations->pluck('id'))
                    ->groupBy('location_id', 'catalog_item_id')
                    ->get()
                    ->keyBy(fn ($row) => $row->location_id.'-'.$row->catalog_item_id);

                $issuableMaterials = StockLevel::with(['catalogItem', 'location'])
                    ->whereIn('location_id', $writeLocations->pluck('id'))
                    ->where('quantity', '>', 0)
                    ->whereHas('catalogItem', fn ($query) => $query->where('active', true)->where('tracking_type', '!=', 'serialized'))
                    ->orderBy('location_id')
                    ->orderBy('catalog_item_id')
                    ->get()
                    ->map(function (StockLevel $stock) use ($heldTotals, $pendingTotals): StockLevel {
                        $key = $stock->location_id.'-'.$stock->catalog_item_id;
                        $stock->setAttribute('available_for_custody', max(
                            0,
                            (float) $stock->quantity
                                - (float) ($heldTotals->get($key)?->quantity ?? 0)
                                - (float) ($pendingTotals->get($key)?->quantity ?? 0),
                        ));

                        return $stock;
                    })
                    ->filter(fn (StockLevel $stock) => $stock->available_for_custody > 0.0005)
                    ->values();
            }
        }

        $availableAssets = TrackedAsset::with(['catalogItem', 'currentLocation'])
            ->whereNull('current_custodian_id')
            ->where('status', 'available')
            ->whereIn('current_location_id', $writeLocations->pluck('id'))
            ->whereDoesntHave('catalogItem', fn ($query) => $query->where('active', false))
            ->orderBy('asset_code')
            ->get();
        $recipients = User::permission('custody.view')
            ->where('active', true)
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get();

        return view('field.worker', [
            'assets' => $assets,
            'custodyTransfers' => $custodyTransfers,
            'pendingDecisions' => $pendingDecisions,
            'materialCustodies' => $materialCustodies,
            'ownAssets' => $assets->where('current_custodian_id', $user->id)->values(),
            'ownMaterialCustodies' => $materialCustodies->where('user_id', $user->id)->values(),
            'availableAssets' => $availableAssets,
            'issuableMaterials' => $issuableMaterials,
            'recipients' => $recipients,
            'returnLocations' => Location::where('active', true)->orderBy('name')->get(),
            'writeLocations' => $writeLocations,
            'canIssueCustody' => $writeLocations->isNotEmpty(),
            'canInitiateCustody' => $user->hasAbility('custody.initiate'),
            'showRecipientCodes' => $user->usesDriverWorkspace(),
            'expandedCustodyAvailable' => $expandedCustodyAvailable,
        ]);
    }
}
