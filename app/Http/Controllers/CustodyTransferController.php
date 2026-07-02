<?php

namespace App\Http\Controllers;

use App\Models\CustodyTransfer;
use App\Models\TrackedAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustodyTransferController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tracked_asset_id' => ['required', 'exists:tracked_assets,id'],
            'to_user_id' => ['required', 'different:from_user_id', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $asset = TrackedAsset::findOrFail($data['tracked_asset_id']);

        CustodyTransfer::create([
            'tracked_asset_id' => $asset->id,
            'from_user_id' => $asset->current_custodian_id ?: $request->user()->id,
            'to_user_id' => $data['to_user_id'],
            'status' => 'pending',
            'qr_token' => 'CUST-'.Str::upper(Str::random(10)),
            'expires_at' => now()->addDay(),
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('status', 'Predarea catre muncitor a fost initiata.');
    }

    public function update(Request $request, CustodyTransfer $custodyTransfer): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:accepted,cancelled'],
        ]);

        DB::transaction(function () use ($custodyTransfer, $data) {
            $updates = ['status' => $data['status']];

            if ($data['status'] === 'accepted') {
                $updates['accepted_at'] = now();
            }

            $custodyTransfer->update($updates);

            if ($data['status'] === 'accepted') {
                $custodyTransfer->trackedAsset()->update([
                    'current_custodian_id' => $custodyTransfer->to_user_id,
                    'status' => 'in_use',
                    'last_verified_at' => now(),
                ]);
            }
        });

        return back()->with('status', 'Predarea a fost actualizata.');
    }
}
