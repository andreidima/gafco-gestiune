<?php

namespace App\Http\Controllers;

use App\Models\DriverRequest;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriverRequestController extends Controller
{
    public function index(): View
    {
        return view('driver-requests.index', [
            'requests' => DriverRequest::with(['site', 'assignedDriver'])->latest()->paginate(20),
            'sites' => Location::where('type', 'site')->where('active', true)->orderBy('name')->get(),
            'drivers' => User::role('sofer')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DriverRequest::create($request->validate([
            'site_id' => ['required', 'exists:locations,id'],
            'needed_at' => ['nullable', 'date'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]) + [
            'number' => 'DR-'.now()->format('Ymd-His'),
            'requested_by' => $request->user()->id,
            'status' => 'open',
        ]);

        return back()->with('status', 'Cererea de sofer a fost creata.');
    }

    public function update(Request $request, DriverRequest $driverRequest): RedirectResponse
    {
        $data = $request->validate([
            'assigned_driver_id' => ['nullable', 'exists:users,id'],
            'status' => ['required', 'in:open,assigned,in_progress,closed,cancelled'],
        ]);
        $driverRequest->update($data + ['assigned_at' => $data['assigned_driver_id'] ? now() : null]);

        return back()->with('status', 'Cererea de sofer a fost actualizata.');
    }
}
