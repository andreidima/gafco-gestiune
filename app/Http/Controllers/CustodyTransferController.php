<?php

namespace App\Http\Controllers;

use App\Models\CustodyTransfer;
use App\Models\User;
use App\Services\CustodyWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustodyTransferController extends Controller
{
    public function __construct(private readonly CustodyWorkflowService $workflow) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeWorkspace($request);

        $request->merge([
            'operation_type' => $request->input('operation_type', 'handoff'),
            'item_type' => $request->input('item_type', 'equipment'),
            'to_user_code' => $request->filled('to_user_code')
                ? mb_strtoupper(trim((string) $request->input('to_user_code')))
                : null,
        ]);
        $data = $request->validate([
            'operation_type' => ['required', 'in:issue,handoff,return'],
            'item_type' => ['required', 'in:equipment,material'],
            'tracked_asset_id' => ['nullable', 'exists:tracked_assets,id'],
            'material_custody_id' => ['nullable', 'exists:material_custodies,id'],
            'catalog_item_id' => ['nullable', 'exists:catalog_items,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'to_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('active', true),
            ],
            'to_user_code' => [
                'nullable',
                Rule::exists('users', 'login_code')->where('active', true),
            ],
            'quantity' => ['nullable', 'numeric', 'gt:0', 'max:99999999999'],
            'return_condition' => ['nullable', 'in:good,used,damaged,needs_service'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if (! isset($data['to_user_id']) && isset($data['to_user_code'])) {
            $data['to_user_id'] = User::where('active', true)
                ->where('login_code', $data['to_user_code'])
                ->value('id');
        }

        $transfer = $this->workflow->initiate($request->user(), $data);
        $message = $transfer->status === 'accepted'
            ? 'Operațiunea '.$transfer->qr_token.' a fost finalizată.'
            : 'Operațiunea '.$transfer->qr_token.' a fost inițiată și așteaptă confirmările afișate.';

        return back()->with('status', $message);
    }

    public function update(Request $request, CustodyTransfer $custodyTransfer): RedirectResponse
    {
        $this->authorizeWorkspace($request);
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'response_notes' => ['nullable', 'required_if:decision,rejected', 'string', 'max:2000'],
            'return_condition' => ['nullable', 'in:good,used,damaged,needs_service'],
        ], [
            'response_notes.required_if' => 'Scrie pe scurt motivul refuzului.',
        ]);

        $outcome = $this->workflow->decide($custodyTransfer, $request->user(), $data);
        if ($outcome) {
            return back()->withErrors(['decision' => match ($outcome) {
                'expired' => 'Această operațiune a expirat. Inițiază una nouă.',
                'asset_unavailable' => 'Operațiunea a fost închisă deoarece echipamentul nu mai este disponibil operațional.',
                'material_unavailable' => 'Operațiunea a fost închisă deoarece stocul disponibil nu mai acoperă cantitatea.',
                default => 'Operațiunea a fost închisă deoarece custodia s-a schimbat între timp.',
            }]);
        }

        return back()->with('status', $data['decision'] === 'rejected'
            ? 'Refuzul și observația au fost înregistrate.'
            : 'Confirmarea a fost înregistrată. Operațiunea se finalizează după toate acordurile necesare.');
    }

    private function authorizeWorkspace(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole([
            'super-admin', 'admin', 'dispecer', 'sef-santier',
            'gestionar-baza', 'sofer', 'muncitor',
        ]), 403);
    }
}
