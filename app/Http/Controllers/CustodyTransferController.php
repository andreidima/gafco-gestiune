<?php

namespace App\Http\Controllers;

use App\Models\CustodyTransfer;
use App\Models\TrackedAsset;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustodyTransferController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOperationsAdmin() || $request->user()->usesWorkerWorkspace(), 403);
        $data = $request->validate([
            'tracked_asset_id' => ['required', 'exists:tracked_assets,id'],
            'to_user_id' => ['required', 'different:from_user_id', Rule::exists('users', 'id')->where('active', true)],
            'notes' => ['nullable', 'string'],
        ]);

        $transfer = DB::transaction(function () use ($data, $request): CustodyTransfer {
            $asset = TrackedAsset::lockForUpdate()->findOrFail($data['tracked_asset_id']);
            if (! in_array($asset->status, ['available', 'in_use'], true)) {
                throw ValidationException::withMessages([
                    'tracked_asset_id' => 'Echipamentul nu poate fi predat cat timp este in transfer, service sau marcat lipsa.',
                ]);
            }
            if (! $request->user()->isOperationsAdmin()) {
                abort_unless((int) $asset->current_custodian_id === (int) $request->user()->id, 403);
            }
            $fromUserId = $asset->current_custodian_id ?: $request->user()->id;

            if ((int) $data['to_user_id'] === (int) $fromUserId) {
                throw ValidationException::withMessages(['to_user_id' => 'Destinatarul trebuie sa fie diferit de persoana care preda.']);
            }

            CustodyTransfer::where('tracked_asset_id', $asset->id)
                ->where('status', 'pending')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->update(['status' => 'expired']);

            if (CustodyTransfer::where('tracked_asset_id', $asset->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages([
                    'tracked_asset_id' => 'Exista deja o predare in asteptare pentru acest echipament.',
                ]);
            }

            $transfer = CustodyTransfer::create([
                'tracked_asset_id' => $asset->id,
                'from_user_id' => $fromUserId,
                'to_user_id' => $data['to_user_id'],
                'status' => 'pending',
                'qr_token' => 'CUST-'.Str::upper(Str::random(10)),
                'expires_at' => now()->addDay(),
                'from_approved_at' => $fromUserId === $request->user()->id ? now() : null,
                'notes' => $data['notes'] ?? null,
            ]);

            User::whereIn('id', [$fromUserId, $data['to_user_id']])
                ->where('id', '!=', $request->user()->id)
                ->get()
                ->each(fn (User $user) => $user->notify(new WorkflowNotification(
                    'Aprobare predare necesara',
                    'Predarea '.$transfer->qr_token.' asteapta acordul tau.',
                    route('field.worker')
                )));

            return $transfer;
        });

        return back()->with('status', 'Predarea '.$transfer->qr_token.' a fost initiata si asteapta acordurile ambelor persoane.');
    }

    public function update(Request $request, CustodyTransfer $custodyTransfer): RedirectResponse
    {
        abort_unless($request->user()->isOperationsAdmin() || $request->user()->usesWorkerWorkspace(), 403);
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
        ]);

        $outcome = DB::transaction(function () use ($custodyTransfer, $data, $request): ?string {
            $custodyTransfer = CustodyTransfer::lockForUpdate()->findOrFail($custodyTransfer->id);
            abort_unless($custodyTransfer->status === 'pending', 422);
            $actorId = $request->user()->id;
            abort_unless(in_array($actorId, [$custodyTransfer->from_user_id, $custodyTransfer->to_user_id], true), 403);

            if ($custodyTransfer->expires_at?->isPast()) {
                $custodyTransfer->update(['status' => 'expired']);

                return 'expired';
            }

            if ($data['decision'] === 'rejected') {
                $custodyTransfer->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                    'rejected_by' => $actorId,
                ]);

                return null;
            }

            $field = $actorId === $custodyTransfer->from_user_id ? 'from_approved_at' : 'to_approved_at';
            $custodyTransfer->update([$field => now()]);
            $custodyTransfer->refresh();

            if ($custodyTransfer->from_approved_at && $custodyTransfer->to_approved_at) {
                $asset = TrackedAsset::lockForUpdate()->findOrFail($custodyTransfer->tracked_asset_id);
                if ((int) $asset->current_custodian_id !== (int) $custodyTransfer->from_user_id) {
                    $custodyTransfer->update([
                        'status' => 'rejected',
                        'rejected_at' => now(),
                        'notes' => trim(($custodyTransfer->notes ? $custodyTransfer->notes."\n" : '').'Predare inchisa automat: custodia s-a schimbat intre timp.'),
                    ]);

                    return 'custody_changed';
                }

                if (! in_array($asset->status, ['available', 'in_use'], true)) {
                    $custodyTransfer->update([
                        'status' => 'rejected',
                        'rejected_at' => now(),
                        'notes' => trim(($custodyTransfer->notes ? $custodyTransfer->notes."\n" : '').'Predare inchisa automat: echipamentul nu mai este disponibil operational.'),
                    ]);

                    return 'asset_unavailable';
                }

                $custodyTransfer->update(['status' => 'accepted', 'accepted_at' => now()]);
                $asset->update([
                    'current_custodian_id' => $custodyTransfer->to_user_id,
                    'status' => 'in_use',
                    'last_verified_at' => now(),
                ]);
            }

            return null;
        });

        if ($outcome === 'expired') {
            return back()->withErrors(['decision' => 'Aceasta predare a expirat. Initiaza una noua.']);
        }
        if ($outcome === 'custody_changed') {
            return back()->withErrors(['decision' => 'Predarea a fost inchisa deoarece custodia s-a schimbat intre timp.']);
        }
        if ($outcome === 'asset_unavailable') {
            return back()->withErrors(['decision' => 'Predarea a fost inchisa deoarece echipamentul nu mai este disponibil operational.']);
        }

        return back()->with('status', 'Decizia a fost inregistrata. Predarea se finalizeaza dupa acordul ambelor persoane.');
    }
}
