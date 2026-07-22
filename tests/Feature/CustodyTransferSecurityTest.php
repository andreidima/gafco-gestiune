<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\CustodyTransfer;
use App\Models\TrackedAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustodyTransferSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_one_pending_handoff_can_exist_for_an_asset(): void
    {
        [$from, $firstRecipient, $secondRecipient, $asset] = $this->handoffFixture();

        $this->actingAs($from)->post(route('custody-transfers.store'), [
            'tracked_asset_id' => $asset->id,
            'to_user_id' => $firstRecipient->id,
        ])->assertRedirect();

        $this->actingAs($from)->from(route('field.worker'))->post(route('custody-transfers.store'), [
            'tracked_asset_id' => $asset->id,
            'to_user_id' => $secondRecipient->id,
        ])->assertSessionHasErrors('tracked_asset_id');

        $this->assertSame(1, CustodyTransfer::where('tracked_asset_id', $asset->id)->where('status', 'pending')->count());
    }

    public function test_expired_or_stale_handoff_cannot_overwrite_current_custody(): void
    {
        [$from, $recipient, $other, $asset] = $this->handoffFixture();
        $expired = CustodyTransfer::create([
            'tracked_asset_id' => $asset->id,
            'from_user_id' => $from->id,
            'to_user_id' => $recipient->id,
            'status' => 'pending',
            'qr_token' => 'CUST-EXPIRED',
            'expires_at' => now()->subMinute(),
            'from_approved_at' => now()->subHour(),
        ]);

        $this->actingAs($recipient)->from(route('field.worker'))->put(route('custody-transfers.update', $expired), [
            'decision' => 'approved',
        ])->assertSessionHasErrors('decision');
        $this->assertSame('expired', $expired->fresh()->status);
        $this->assertSame($from->id, $asset->fresh()->current_custodian_id);

        $stale = CustodyTransfer::create([
            'tracked_asset_id' => $asset->id,
            'from_user_id' => $from->id,
            'to_user_id' => $recipient->id,
            'status' => 'pending',
            'qr_token' => 'CUST-STALE',
            'expires_at' => now()->addHour(),
            'from_approved_at' => now(),
        ]);
        $asset->update(['current_custodian_id' => $other->id]);

        $this->actingAs($recipient)->put(route('custody-transfers.update', $stale), [
            'decision' => 'approved',
        ])->assertRedirect();

        $this->assertSame('rejected', $stale->fresh()->status);
        $this->assertSame($other->id, $asset->fresh()->current_custodian_id);
    }

    public function test_non_operational_asset_cannot_start_or_finish_a_handoff(): void
    {
        [$from, $recipient, $other, $asset] = $this->handoffFixture();
        $asset->update(['status' => 'maintenance']);

        $this->actingAs($from)->post(route('custody-transfers.store'), [
            'tracked_asset_id' => $asset->id,
            'to_user_id' => $recipient->id,
        ])->assertSessionHasErrors('tracked_asset_id');

        $asset->update(['status' => 'in_use']);
        $this->actingAs($from)->post(route('custody-transfers.store'), [
            'tracked_asset_id' => $asset->id,
            'to_user_id' => $recipient->id,
        ])->assertRedirect();
        $handoff = CustodyTransfer::latest('id')->firstOrFail();
        $asset->update(['status' => 'in_transfer']);

        $this->actingAs($recipient)->put(route('custody-transfers.update', $handoff), [
            'decision' => 'approved',
        ])->assertSessionHasErrors('decision');

        $this->assertSame('rejected', $handoff->fresh()->status);
        $this->assertSame($from->id, $asset->fresh()->current_custodian_id);
        $this->assertSame('in_transfer', $asset->fresh()->status);
    }

    private function handoffFixture(): array
    {
        Role::findOrCreate('muncitor');
        $from = User::factory()->create(['login_code' => 'CUST-FROM']);
        $firstRecipient = User::factory()->create(['login_code' => 'CUST-TO-1']);
        $secondRecipient = User::factory()->create(['login_code' => 'CUST-TO-2']);
        $from->assignRole('muncitor');
        $firstRecipient->assignRole('muncitor');
        $secondRecipient->assignRole('muncitor');
        $item = CatalogItem::create([
            'category' => 'equipment',
            'tracking_type' => 'serialized',
            'sku' => 'CUST-EQP',
            'name' => 'Echipament custodie',
            'unit' => 'buc',
            'active' => true,
        ]);
        $asset = TrackedAsset::create([
            'catalog_item_id' => $item->id,
            'asset_code' => 'CUST-ASSET',
            'qr_code' => 'QR-CUST-ASSET',
            'status' => 'in_use',
            'condition' => 'good',
            'current_custodian_id' => $from->id,
        ]);

        return [$from, $firstRecipient, $secondRecipient, $asset];
    }
}
