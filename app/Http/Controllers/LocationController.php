<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\StockLevel;
use App\Models\TrackedAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $locationsForInsights = Location::where('active', true)->get();
        $topLocation = $locationsForInsights
            ->map(function (Location $location) {
                $location->assets_count = TrackedAsset::where('current_location_id', $location->id)->count();

                return $location;
            })
            ->sortByDesc('assets_count')
            ->first();

        return view('locations.index', [
            'locations' => Location::with('manager')
                ->when($request->type, fn ($query, $type) => $query->where('type', $type))
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
            'stats' => [
                'total' => Location::count(),
                'sites' => Location::where('type', 'site')->count(),
                'bases' => Location::where('type', 'base')->count(),
                'with_manager' => Location::whereNotNull('manager_user_id')->count(),
                'stock_positions' => StockLevel::where('quantity', '>', 0)->count(),
                'assets' => TrackedAsset::count(),
            ],
            'insights' => [
                'top_location' => $topLocation,
                'without_manager' => Location::whereNull('manager_user_id')->where('active', true)->count(),
                'latest' => Location::latest()->first(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Location::create($request->validate([
            'type' => ['required', 'in:base,site'],
            'code' => ['required', 'string', 'max:40', 'unique:locations,code'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]) + ['active' => true]);

        return back()->with('status', 'Locatia a fost adaugata.');
    }
}
