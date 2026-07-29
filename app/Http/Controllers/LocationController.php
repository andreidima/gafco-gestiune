<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\User;
use App\Services\LocationAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function __construct(private readonly LocationAccessService $locationAccess) {}

    public function index(Request $request): View
    {
        $visibleLocationIds = $this->locationAccess->visibleLocationIds($request->user());

        return view('locations.index', [
            'locations' => Location::with('activeManagers')
                ->withCount([
                    'trackedAssets',
                    'stockLevels',
                    'trackedAssets as attention_assets_count' => fn ($query) => $query->where(function ($attentionQuery) {
                        $attentionQuery->whereIn('status', ['maintenance', 'lost'])
                            ->orWhereIn('condition', ['damaged', 'needs_service']);
                    }),
                    'stockLevels as empty_stock_levels_count' => fn ($query) => $query->where('quantity', '<=', 0),
                    'transferApprovals as pending_transfer_approvals_count' => fn ($query) => $query
                        ->where('status', 'pending')
                        ->whereExists(fn ($transferQuery) => $transferQuery
                            ->selectRaw('1')
                            ->from('transfers')
                            ->whereColumn('transfers.id', 'transfer_approvals.transfer_id')
                            ->whereColumn('transfers.revision', 'transfer_approvals.revision')
                            ->whereNull('transfers.cancelled_at')
                            ->whereNull('transfers.archived_at')),
                ])
                ->when($visibleLocationIds !== null, fn ($query) => $query->whereIn('locations.id', $visibleLocationIds))
                ->when($request->type, fn ($query, $type) => $query->where('type', $type))
                ->when($request->filled('active'), fn ($query) => $query->where('active', $request->boolean('active')))
                ->when($request->search, fn ($query, $search) => $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                }))
                ->orderBy('type')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'totalLocations' => Location::query()
                ->when($visibleLocationIds !== null, fn ($query) => $query->whereIn('id', $visibleLocationIds))
                ->count(),
        ]);
    }

    public function create(): View
    {
        return view('locations.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($data): void {
            $managerIds = array_values($data['manager_user_ids'] ?? []);
            unset($data['manager_user_ids']);
            $location = Location::create($data + [
                'active' => $data['active'] ?? true,
                'manager_user_id' => $managerIds[0] ?? null,
            ]);
            $location->managers()->sync($this->managerSyncData($managerIds));
        });

        return redirect()->route('locations.index')->with('status', 'Locatia a fost adaugata.');
    }

    public function edit(Location $location): View
    {
        $location->load('activeManagers');

        return view('locations.form', $this->formData($location));
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $data = $this->validatedData($request, $location);
        $managerIds = array_values($data['manager_user_ids'] ?? []);
        unset($data['manager_user_ids']);

        DB::transaction(function () use ($location, $managerIds, $data): void {
            $location->managers()->sync($this->managerSyncData($managerIds));
            $location->update($data + ['manager_user_id' => $managerIds[0] ?? null]);
        });

        return redirect()->route('locations.index')->with('status', 'Locatia a fost actualizata.');
    }

    private function managerSyncData(array $managerIds): array
    {
        return collect($managerIds)->mapWithKeys(
            fn (int|string $id, int $index) => [(int) $id => ['active' => true, 'is_primary' => $index === 0]]
        )->all();
    }

    private function formData(?Location $location = null): array
    {
        return [
            'location' => $location,
            'managers' => User::where('active', true)
                ->whereHas('roles', fn ($query) => $query->whereIn('name', ['sef-santier', 'gestionar-baza', 'dispecer', 'admin', 'super-admin']))
                ->with('roles')
                ->orderBy('name')
                ->get(),
        ];
    }

    private function validatedData(Request $request, ?Location $location = null): array
    {
        $request->merge([
            'code' => Str::upper(trim((string) $request->input('code'))),
        ]);

        return $request->validate([
            'type' => ['required', 'in:base,site'],
            'code' => ['required', 'string', 'max:40', Rule::unique('locations', 'code')->ignore($location)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'manager_user_ids' => ['nullable', 'array'],
            'manager_user_ids.*' => ['integer', 'exists:users,id'],
            'active' => ['nullable', 'boolean'],
        ]);
    }
}
