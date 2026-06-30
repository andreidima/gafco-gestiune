<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\Location;
use App\Models\Supplier;
use App\Models\SupplierReception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupplierReceptionController extends Controller
{
    public function index(): View
    {
        return view('supplier-receptions.index', [
            'receptions' => SupplierReception::with(['location', 'supplier'])->withCount('lines')->latest()->paginate(20),
            'locations' => Location::where('active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::where('active', true)->orderBy('name')->get(),
            'items' => CatalogItem::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'exists:locations,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'document_type' => ['required', 'in:aviz,factura'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'catalog_item_id' => ['required', 'exists:catalog_items,id'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $item = CatalogItem::findOrFail($data['catalog_item_id']);
            $reception = SupplierReception::create([
                'number' => 'RF-'.now()->format('Ymd-His'),
                'location_id' => $data['location_id'],
                'supplier_id' => $data['supplier_id'] ?: null,
                'received_by' => $request->user()->id,
                'document_type' => $data['document_type'],
                'document_number' => $data['document_number'] ?? null,
                'status' => 'posted',
                'received_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);
            $reception->lines()->create([
                'catalog_item_id' => $item->id,
                'quantity' => $data['quantity'],
                'unit' => $item->unit,
            ]);
        });

        return back()->with('status', 'Receptia a fost salvata.');
    }
}
