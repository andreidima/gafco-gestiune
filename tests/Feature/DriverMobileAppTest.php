<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Task;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\TaskWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use NotificationChannels\WebPush\WebPushChannel;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DriverMobileAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_workspace_exposes_the_installable_mobile_shell_and_next_action(): void
    {
        [$manager, $driver] = $this->users();
        $task = Task::create([
            'number' => 'TSK-PWA-001',
            'title' => 'Transport pentru aplicația instalată',
            'category' => 'transport',
            'created_by' => $manager->id,
            'status' => 'unassigned',
            'priority' => 'normal',
            'manager_deadline' => now()->addHours(3),
        ]);
        app(TaskWorkflowService::class)->assign($task, $driver, $manager);

        config()->set('webpush.vapid.public_key', 'public-test-key');
        config()->set('webpush.vapid.private_key', 'private-test-key');

        $this->actingAs($driver)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('manifest.webmanifest', false)
            ->assertSee('driver-workspace', false)
            ->assertSee('driver-mobile-topbar', false)
            ->assertSee('driver-bottom-nav', false)
            ->assertSeeInOrder(['Sarcini', 'Transferuri', 'QR', 'Custodie', 'Notificări'])
            ->assertSee('data-driver-app-controls', false)
            ->assertSee('Următoarea acțiune: Răspunde la alocare')
            ->assertSee('Transport pentru aplicația instalată');

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('manifest.webmanifest', false)
            ->assertDontSee('driver-bottom-nav', false);
    }

    public function test_manifest_service_worker_and_offline_fallback_are_present(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
        $serviceWorker = (string) file_get_contents(public_path('sw.js'));

        $this->assertSame('GAFCO Șofer', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/acasa?source=pwa', $manifest['start_url']);
        $this->assertCount(3, $manifest['icons']);
        $this->assertFileExists(public_path('icons/gafco-driver-192.png'));
        $this->assertFileExists(public_path('icons/gafco-driver-512.png'));
        $this->assertStringContainsString("request.mode === 'navigate'", $serviceWorker);
        $this->assertStringContainsString("caches.match('/offline')", $serviceWorker);
        $this->assertStringContainsString("fetch('/build/manifest.json'", $serviceWorker);
        $this->assertStringContainsString("self.addEventListener('push'", $serviceWorker);

        $this->get(route('pwa.offline'))
            ->assertOk()
            ->assertSee('Nu există conexiune la internet')
            ->assertSee('nu sunt salvate offline');
    }

    public function test_driver_can_register_and_remove_only_their_own_push_subscription(): void
    {
        [$manager, $driver] = $this->users();
        $payload = [
            'endpoint' => 'https://push.example.test/subscriptions/driver-device',
            'keys' => [
                'p256dh' => 'driver-public-key',
                'auth' => 'driver-auth-token',
            ],
            'content_encoding' => 'aes128gcm',
        ];

        $this->actingAs($manager)
            ->putJson(route('push-subscriptions.store'), $payload)
            ->assertForbidden();

        $this->actingAs($driver)
            ->putJson(route('push-subscriptions.store'), $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Notificările au fost activate pe acest dispozitiv.');

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_id' => $driver->id,
            'subscribable_type' => $driver->getMorphClass(),
            'endpoint' => $payload['endpoint'],
            'content_encoding' => 'aes128gcm',
        ]);

        $this->actingAs($driver)
            ->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => $payload['endpoint']])
            ->assertOk()
            ->assertJsonPath('message', 'Notificările au fost dezactivate pe acest dispozitiv.');

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => $payload['endpoint']]);
    }

    public function test_workflow_notifications_add_web_push_only_for_configured_driver_devices(): void
    {
        [$manager, $driver] = $this->users();
        $notification = new WorkflowNotification(
            'Sarcină nouă',
            'Ai primit o sarcină nouă.',
            'https://gafco.example.test/sarcini/42?din=notificare',
        );

        $this->assertSame(['database'], $notification->via($driver));

        $driver->updatePushSubscription(
            'https://push.example.test/subscriptions/driver-device',
            'driver-public-key',
            'driver-auth-token',
            'aes128gcm',
        );
        config()->set('webpush.vapid.public_key', 'public-test-key');
        config()->set('webpush.vapid.private_key', 'private-test-key');

        $this->assertContains(WebPushChannel::class, $notification->via($driver));
        $this->assertSame(['database'], $notification->via($manager));
        $this->assertSame(
            '/sarcini/42?din=notificare',
            $notification->toWebPush($driver, $notification)->toArray()['data']['url'],
        );
    }

    public function test_driver_task_shows_external_route_actions(): void
    {
        [$manager, $driver] = $this->users();
        $source = Location::create([
            'type' => 'base',
            'code' => 'BAZA',
            'name' => 'Baza GAFCO',
            'address' => 'Strada Fabricii 1, Botoșani',
            'active' => true,
        ]);
        $destination = Location::create([
            'type' => 'site',
            'code' => 'SANTIER',
            'name' => 'Șantier Central',
            'address' => 'Calea Națională 10, Botoșani',
            'active' => true,
        ]);
        $task = Task::create([
            'number' => 'TSK-PWA-MAP',
            'title' => 'Transport cu navigare',
            'category' => 'transport',
            'created_by' => $manager->id,
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'status' => 'unassigned',
            'priority' => 'normal',
        ]);
        app(TaskWorkflowService::class)->assign($task, $driver, $manager);

        $this->actingAs($driver)->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('https://www.google.com/maps/dir/?api=1&amp;destination=', false)
            ->assertSee('https://www.waze.com/ul?q=', false)
            ->assertSee('Traseu')
            ->assertSee('Waze');
    }

    public function test_driver_mobile_help_article_and_release_note_migration_are_reversible(): void
    {
        $migration = require database_path('migrations/2026_08_04_000040_publish_driver_pwa_content.php');

        DB::connection()->pretend(fn () => $migration->up());
        $this->assertDatabaseHas('help_articles', [
            'slug' => 'aplicatia-pentru-soferi',
            'current_revision' => 1,
            'status' => 'published',
        ]);
        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-08-04-aplicatie-instalabila-pentru-soferi',
            'version' => '2026.08.04.5',
            'requires_action' => true,
            'status' => 'published',
        ]);

        $migration->down();
        $this->assertDatabaseMissing('help_articles', ['slug' => 'aplicatia-pentru-soferi']);
        $this->assertDatabaseMissing('release_notes', ['slug' => '2026-08-04-aplicatie-instalabila-pentru-soferi']);

        $migration->up();
        $this->assertDatabaseHas('help_articles', ['slug' => 'aplicatia-pentru-soferi']);
        $this->assertDatabaseHas('release_notes', ['slug' => '2026-08-04-aplicatie-instalabila-pentru-soferi']);
    }

    private function users(): array
    {
        Role::findOrCreate('sef-santier');
        Role::findOrCreate('sofer');
        $manager = User::factory()->create(['login_code' => 'PWA-MANAGER']);
        $manager->assignRole('sef-santier');
        $driver = User::factory()->create(['login_code' => 'PWA-DRIVER']);
        $driver->assignRole('sofer');

        return [$manager, $driver];
    }
}
