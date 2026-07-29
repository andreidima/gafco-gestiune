<?php

namespace App\Http\Controllers;

use App\Models\NegotiatedOrder;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $suppliers = Supplier::query()
            ->withCount([
                'receptions',
                'negotiatedOrders',
                'negotiatedOrders as open_negotiated_orders_count' => fn ($query) => $query
                    ->where('status', NegotiatedOrder::STATUS_CREATED),
            ])
            ->withMax('receptions as last_reception_at', 'received_at')
            ->when($request->search, function ($query, $search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('cui', 'like', "%{$search}%")
                        ->orWhere('registration_number', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->orderByDesc('active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'totalSuppliers' => Supplier::count(),
            'activeSuppliers' => Supplier::where('active', true)->count(),
            'canManage' => $request->user()->can('suppliers.manage'),
        ]);
    }

    public function create(): View
    {
        return view('suppliers.form', [
            'supplier' => null,
            'openOrderCount' => 0,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $supplier = Supplier::create($this->validatedData($request) + ['active' => true]);

        activity()
            ->performedOn($supplier)
            ->causedBy($request->user())
            ->withProperties(['after' => $this->snapshot($supplier)])
            ->log('Furnizor adăugat');

        return redirect()
            ->route('suppliers.index')
            ->with('status', 'Furnizorul a fost adăugat.');
    }

    public function edit(Supplier $supplier): View
    {
        $supplier->loadCount([
            'negotiatedOrders as open_negotiated_orders_count' => fn ($query) => $query
                ->where('status', NegotiatedOrder::STATUS_CREATED),
        ]);

        return view('suppliers.form', [
            'supplier' => $supplier,
            'openOrderCount' => (int) $supplier->open_negotiated_orders_count,
        ]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $before = $this->snapshot($supplier);
        $supplier->update($this->validatedData($request, $supplier));

        activity()
            ->performedOn($supplier)
            ->causedBy($request->user())
            ->withProperties([
                'before' => $before,
                'after' => $this->snapshot($supplier->fresh()),
            ])
            ->log('Furnizor actualizat');

        return redirect()
            ->route('suppliers.index')
            ->with('status', 'Datele furnizorului au fost actualizate.');
    }

    public function deactivate(Request $request, Supplier $supplier): RedirectResponse
    {
        $openOrderCount = 0;

        DB::transaction(function () use ($supplier, &$openOrderCount): void {
            $lockedSupplier = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);
            $openOrderCount = $lockedSupplier->negotiatedOrders()
                ->where('status', NegotiatedOrder::STATUS_CREATED)
                ->count();

            if ($openOrderCount > 0) {
                return;
            }

            $lockedSupplier->update(['active' => false]);
        });

        if ($openOrderCount > 0) {
            $orders = $openOrderCount === 1
                ? 'o comandă negociată deschisă'
                : "{$openOrderCount} comenzi negociate deschise";

            return redirect()
                ->route('suppliers.edit', $supplier)
                ->withErrors([
                    'active' => "Furnizorul nu poate fi dezactivat deoarece are {$orders}. Închide sau anulează comenzile înainte să dezactivezi furnizorul.",
                ]);
        }

        activity()
            ->performedOn($supplier)
            ->causedBy($request->user())
            ->withProperties(['active' => false])
            ->log('Furnizor dezactivat');

        return redirect()
            ->route('suppliers.index')
            ->with('status', 'Furnizorul a fost dezactivat. Rămâne vizibil în istoricul documentelor.');
    }

    public function activate(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update(['active' => true]);

        activity()
            ->performedOn($supplier)
            ->causedBy($request->user())
            ->withProperties(['active' => true])
            ->log('Furnizor reactivat');

        return redirect()
            ->route('suppliers.index')
            ->with('status', 'Furnizorul a fost reactivat și poate fi ales în documente noi.');
    }

    private function validatedData(Request $request, ?Supplier $supplier = null): array
    {
        $request->merge(['cui' => Supplier::formatCui($request->input('cui'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cui' => ['nullable', 'string', 'max:32'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:2000'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $normalizedCui = Supplier::normalizeCui($data['cui'] ?? null);
        if ($normalizedCui !== null) {
            $duplicateExists = Supplier::query()
                ->whereNotNull('cui')
                ->when($supplier, fn ($query) => $query->whereKeyNot($supplier->id))
                ->get(['id', 'cui'])
                ->contains(fn (Supplier $existing) => Supplier::normalizeCui($existing->cui) === $normalizedCui);

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'cui' => 'Există deja un furnizor cu acest CUI.',
                ]);
            }
        }

        return $data + ['normalized_cui' => $normalizedCui];
    }

    private function snapshot(Supplier $supplier): array
    {
        return $supplier->only([
            'name',
            'cui',
            'registration_number',
            'address',
            'contact_person',
            'email',
            'phone',
            'notes',
            'active',
        ]);
    }
}
