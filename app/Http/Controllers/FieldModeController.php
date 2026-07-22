<?php

namespace App\Http\Controllers;

use App\Models\ConsumptionReport;
use App\Models\CustodyTransfer;
use App\Models\Location;
use App\Models\Task;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\TransferApproval;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FieldModeController extends Controller
{
    public function driver(): RedirectResponse
    {
        return redirect()->route('tasks.index');
    }

    public function siteManager(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isOperationsAdmin() || $user->hasAnyRole(['sef-santier', 'gestionar-baza']), 403);

        $managedLocationIds = $user->activeManagedLocations()->pluck('locations.id');
        $visibleTransfers = Transfer::query()
            ->whereIn('status', ['pending_approval', 'approved', 'in_transit'])
            ->when(! $user->isOperationsAdmin(), fn ($query) => $query->where(function ($visible) use ($managedLocationIds): void {
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
            ->when($user->isOperationsAdmin(), fn ($query) => $query->where('scope', '!=', 'driver'))
            ->whereHas('transfer', fn ($transfer) => $transfer
                ->whereColumn('transfers.revision', 'transfer_approvals.revision')
                ->whereNotIn('status', ['received', 'cancelled']))
            ->when(! $user->isOperationsAdmin(), fn ($query) => $query->whereIn('location_id', $managedLocationIds));
        $pendingApprovalsCount = (clone $pendingApprovalsQuery)->count();
        $pendingApprovals = $pendingApprovalsQuery
            ->with(['transfer.sourceLocation', 'transfer.destinationLocation', 'location'])
            ->latest()
            ->limit(12)
            ->get();

        $visibleTasks = Task::query()
            ->when(! $user->isOperationsAdmin(), fn ($query) => $query->where(function ($visible) use ($user, $managedLocationIds): void {
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
            ->when(! $user->isOperationsAdmin(), fn ($query) => $query->whereIn('location_id', $managedLocationIds));

        return view('field.site-manager', [
            'pendingTransfers' => $pendingTransfers,
            'pendingApprovals' => $pendingApprovals,
            'pendingApprovalsCount' => $pendingApprovalsCount,
            'activeTransfersCount' => $activeTransfersCount,
            'overdueTasksCount' => $overdueTasksCount,
            'consumptionThisMonthCount' => (clone $recentConsumptionQuery)->where('reported_at', '>=', now()->subDays(30))->count(),
            'managedLocationsCount' => $user->isOperationsAdmin() ? Location::where('active', true)->count() : $managedLocationIds->count(),
            'recentConsumption' => $recentConsumptionQuery
                ->latest('reported_at')
                ->limit(8)
                ->get(),
        ]);
    }

    public function worker(Request $request): View
    {
        $user = $request->user();
        $assets = TrackedAsset::with(['catalogItem', 'currentLocation', 'currentCustodian'])
            ->whereNotNull('current_custodian_id')
            ->whereIn('status', ['available', 'in_use'])
            ->when(! $user->isOperationsAdmin(), fn ($query) => $query->where('current_custodian_id', $user->id))
            ->latest('last_verified_at')
            ->limit(40)
            ->get();
        $custodyTransfers = CustodyTransfer::with(['trackedAsset.catalogItem', 'fromUser', 'toUser'])
            ->when(! $user->isOperationsAdmin(), fn ($query) => $query->where(function ($visible) use ($user): void {
                $visible->where('from_user_id', $user->id)->orWhere('to_user_id', $user->id);
            }))
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->search, fn ($query, $search) => $query->where(function ($filtered) use ($search): void {
                $filtered->where('qr_token', 'like', "%{$search}%")
                    ->orWhereHas('trackedAsset', fn ($asset) => $asset->where('asset_code', 'like', "%{$search}%"));
            }))
            ->latest()
            ->limit(30)
            ->get();

        return view('field.worker', [
            'assets' => $assets,
            'custodyTransfers' => $custodyTransfers,
            'workers' => User::role('muncitor')->orderBy('name')->get(),
        ]);
    }
}
