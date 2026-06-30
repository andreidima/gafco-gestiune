@extends('layouts.app')

@section('title', 'Cereri sofer')
@section('page_title', 'Cereri sofer')
@section('page_subtitle', 'Dispecerat pentru santiere')

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <form method="post" action="{{ route('driver-requests.store') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-3">
                    <label class="form-label">Santier</label>
                    <select name="site_id" class="form-select" data-tom-select required>
                        <option value="">Alege santier</option>
                        @foreach($sites as $site)
                            <option value="{{ $site->id }}">{{ $site->code }} - {{ $site->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Necesitate</label>
                    <input name="needed_at" type="datetime-local" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ridicare</label>
                    <input name="pickup_address" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Livrare</label>
                    <input name="delivery_address" class="form-control">
                </div>
                <div class="col-md-1">
                    <button class="btn btn-success w-100">Cere</button>
                </div>
                <div class="col-12">
                    <input name="notes" class="form-control" placeholder="Observatii pentru dispecerat">
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Numar</th>
                        <th>Santier</th>
                        <th>Traseu</th>
                        <th>Sofer</th>
                        <th>Status</th>
                        <th class="text-end">Dispecerat</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($requests as $driverRequest)
                    <tr>
                        <td>
                            <strong>{{ $driverRequest->number }}</strong>
                            <div class="small text-secondary">{{ optional($driverRequest->needed_at)->format('d.m.Y H:i') ?? 'Fara data' }}</div>
                        </td>
                        <td>{{ $driverRequest->site?->name }}</td>
                        <td>
                            <div>{{ $driverRequest->pickup_address ?: 'Ridicare nespecificata' }}</div>
                            <div class="small text-secondary">{{ $driverRequest->delivery_address ?: 'Livrare nespecificata' }}</div>
                        </td>
                        <td>{{ $driverRequest->assignedDriver?->name ?? 'Nealocat' }}</td>
                        <td><x-status :status="$driverRequest->status" /></td>
                        <td>
                            <form method="post" action="{{ route('driver-requests.update', $driverRequest) }}" class="d-flex justify-content-end gap-2">
                                @csrf
                                @method('put')
                                <select name="assigned_driver_id" class="form-select form-select-sm" style="max-width: 150px">
                                    <option value="">Fara sofer</option>
                                    @foreach($drivers as $driver)
                                        <option value="{{ $driver->id }}" @selected($driverRequest->assigned_driver_id === $driver->id)>{{ $driver->name }}</option>
                                    @endforeach
                                </select>
                                <select name="status" class="form-select form-select-sm" style="max-width: 130px">
                                    @foreach(['open', 'assigned', 'in_progress', 'closed', 'cancelled'] as $status)
                                        <option value="{{ $status }}" @selected($driverRequest->status === $status)>{{ str_replace('_', ' ', $status) }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-sm btn-outline-primary">Salveaza</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">Nu exista cereri.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $requests->links() }}</div>
    </div>
@endsection
