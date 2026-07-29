<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FilterPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dropdown_filters_are_saved_per_user_while_text_is_not_saved(): void
    {
        $firstAdmin = $this->userWithRole('admin');
        $secondAdmin = $this->userWithRole('admin');
        $base = $this->location('B-FILTER', 'base', true);
        $activeSite = $this->location('S-ACTIVE', 'site', true);
        $inactiveSite = $this->location('S-INACTIVE', 'site', false);

        $this->actingAs($firstAdmin)->get(route('locations.index', [
            'filters_submitted' => 1,
            'type' => 'site',
            'active' => '1',
            'search' => 'nu-există',
        ]))->assertOk()
            ->assertDontSee($base->name)
            ->assertDontSee($activeSite->name)
            ->assertDontSee($inactiveSite->name);

        $preference = UserPreference::query()
            ->where('user_id', $firstAdmin->id)
            ->where('key', 'filters.locations.index')
            ->sole();

        $this->assertSame(['type' => 'site', 'active' => '1'], $preference->value);

        $this->actingAs($firstAdmin)->get(route('locations.index'))
            ->assertOk()
            ->assertSee($activeSite->name)
            ->assertDontSee($base->name)
            ->assertDontSee($inactiveSite->name)
            ->assertSee('name="search" value=""', false);

        $this->actingAs($secondAdmin)->get(route('locations.index'))
            ->assertOk()
            ->assertSee($base->name)
            ->assertSee($activeSite->name)
            ->assertSee($inactiveSite->name);
    }

    public function test_direct_context_filter_overrides_saved_value_without_replacing_it_and_reset_clears_it(): void
    {
        $admin = $this->userWithRole('admin');
        $base = $this->location('B-CONTEXT', 'base', true);
        $site = $this->location('S-CONTEXT', 'site', true);

        $this->actingAs($admin)->get(route('locations.index', [
            'filters_submitted' => 1,
            'type' => 'site',
        ]))->assertOk();

        $this->actingAs($admin)->get(route('locations.index', ['type' => 'base']))
            ->assertOk()
            ->assertSee($base->name)
            ->assertDontSee($site->name);

        $this->assertSame(
            ['type' => 'site'],
            UserPreference::query()
                ->where('user_id', $admin->id)
                ->where('key', 'filters.locations.index')
                ->sole()
                ->value,
        );

        $this->actingAs($admin)->get(route('locations.index'))
            ->assertOk()
            ->assertSee($site->name)
            ->assertDontSee($base->name);

        $this->actingAs($admin)->get(route('locations.index', ['filters_reset' => 1]))
            ->assertOk()
            ->assertSee($base->name)
            ->assertSee($site->name);

        $this->assertSame(
            [],
            UserPreference::query()
                ->where('user_id', $admin->id)
                ->where('key', 'filters.locations.index')
                ->sole()
                ->value,
        );
    }

    public function test_checkbox_filters_are_saved_and_can_be_cleared(): void
    {
        $manager = $this->userWithRole('manager');
        $location = $this->location('B-CHECKBOX', 'base', true);
        $zeroItem = $this->item('MAT-ZERO-CHECK', 'Material zero checkbox');
        $positiveItem = $this->item('MAT-POS-CHECK', 'Material pozitiv checkbox');
        StockLevel::create([
            'location_id' => $location->id,
            'catalog_item_id' => $positiveItem->id,
            'quantity' => 4,
        ]);

        $this->actingAs($manager)->get(route('inventory.index', [
            'filters_submitted' => 1,
            'hide_zero' => 1,
        ]))->assertOk()
            ->assertSee($positiveItem->name)
            ->assertDontSee($zeroItem->name);

        $this->actingAs($manager)->get(route('inventory.index'))
            ->assertOk()
            ->assertViewHas('filters', fn (array $filters): bool => $filters['hide_zero'] === true)
            ->assertSee($positiveItem->name)
            ->assertDontSee($zeroItem->name);

        $this->actingAs($manager)->get(route('inventory.index', [
            'filters_submitted' => 1,
            'hide_zero' => 0,
        ]))->assertOk();

        $this->actingAs($manager)->get(route('inventory.index'))
            ->assertOk()
            ->assertViewHas('filters', fn (array $filters): bool => $filters['hide_zero'] === false)
            ->assertSee($zeroItem->name)
            ->assertSee($positiveItem->name);
    }

    public function test_impersonated_filter_changes_do_not_modify_the_target_preferences(): void
    {
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('manager');
        $this->location('B-IMPERSONATED', 'base', true);

        UserPreference::create([
            'user_id' => $manager->id,
            'key' => 'filters.locations.index',
            'value' => ['type' => 'site'],
        ]);

        $this->actingAs($admin)
            ->post(route('impersonation.take', $manager))
            ->assertRedirect(route('dashboard'));

        $this->get(route('locations.index', [
            'filters_submitted' => 1,
            'type' => 'base',
        ]))->assertOk();

        $this->assertSame(
            ['type' => 'site'],
            UserPreference::query()
                ->where('user_id', $manager->id)
                ->where('key', 'filters.locations.index')
                ->sole()
                ->value,
        );
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role);
        $user = User::factory()->create(['active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function location(string $code, string $type, bool $active): Location
    {
        return Location::create([
            'type' => $type,
            'code' => $code,
            'name' => $code,
            'active' => $active,
        ]);
    }

    private function item(string $sku, string $name): CatalogItem
    {
        return CatalogItem::create([
            'category' => 'material',
            'tracking_type' => 'quantity',
            'sku' => $sku,
            'name' => $name,
            'unit' => 'buc',
            'active' => true,
        ]);
    }
}
