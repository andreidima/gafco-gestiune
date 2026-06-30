<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Request $request): View
    {
        return view('locations.index', [
            'locations' => Location::with('manager')
                ->when($request->type, fn ($query, $type) => $query->where('type', $type))
                ->when($request->search, fn ($query, $search) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%"))
                ->orderBy('type')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
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
