<?php

namespace App\Http\Controllers;

use App\Models\TransferApproval;
use App\Services\TransferWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TransferApprovalController extends Controller
{
    public function update(Request $request, TransferApproval $approval, TransferWorkflowService $workflow): RedirectResponse
    {
        $approval->loadMissing('transfer');
        abort_if($approval->scope === 'driver', 403);
        $this->authorize('approve', $approval->transfer);
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'decision_note' => ['nullable', 'required_if:decision,rejected', 'string'],
        ]);
        $workflow->decide($approval, $request->user(), $data['decision'], $data['decision_note'] ?? null);

        return back()->with('status', 'Decizia a fost inregistrata.');
    }
}
