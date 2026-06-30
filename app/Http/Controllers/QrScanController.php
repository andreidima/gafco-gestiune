<?php

namespace App\Http\Controllers;

use App\Models\TrackedAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QrScanController extends Controller
{
    public function index(): View
    {
        return view('qr.index');
    }

    public function lookup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:120'],
        ]);

        $asset = TrackedAsset::where('qr_code', $data['code'])
            ->orWhere('asset_code', $data['code'])
            ->first();

        if (! $asset) {
            return back()
                ->withErrors(['code' => 'Nu am gasit echipament pentru codul scanat.'])
                ->withInput();
        }

        $asset->update(['last_verified_at' => now()]);

        return redirect()->route('tracked-assets.show', $asset)
            ->with('status', 'Cod QR identificat. Locatia si istoricul sunt afisate mai jos.');
    }
}
