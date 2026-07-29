<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->normalizeLocationCodes();
            $this->protectSuperAdministrator();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // The normalized codes and repaired role assignments are intentionally
        // retained. Production rollback uses the managed pre-migration backup.
    }

    private function normalizeLocationCodes(): void
    {
        $locations = DB::table('locations')
            ->select(['id', 'code'])
            ->orderBy('id')
            ->get()
            ->map(fn (object $location): array => [
                'id' => (int) $location->id,
                'original' => (string) $location->code,
                'normalized' => Str::upper(trim((string) $location->code)),
            ]);

        $collisions = $locations
            ->groupBy('normalized')
            ->filter(fn ($group, string $code): bool => $code === '' || $group->count() > 1);

        if ($collisions->isNotEmpty()) {
            $codes = $collisions->keys()->map(fn (string $code): string => $code === '' ? '(gol)' : $code)->join(', ');

            throw new RuntimeException(
                "Normalizarea codurilor de locație ar crea valori duplicate sau goale: {$codes}."
            );
        }

        foreach ($locations as $location) {
            if ($location['original'] !== $location['normalized']) {
                DB::table('locations')
                    ->where('id', $location['id'])
                    ->update([
                        'code' => $location['normalized'],
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    private function protectSuperAdministrator(): void
    {
        if (! DB::table('users')->exists()) {
            return;
        }

        $protectedEmail = Str::lower(trim((string) config('roles.protected_admin_email')));
        $protectedUsers = DB::table('users')
            ->whereRaw('LOWER(TRIM(email)) = ?', [$protectedEmail])
            ->pluck('id');

        if ($protectedUsers->count() !== 1) {
            throw new RuntimeException(
                "Contul protejat {$protectedEmail} trebuie să existe exact o dată înaintea migrării."
            );
        }

        $roleId = DB::table('roles')
            ->where('name', 'super-admin')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $roleId) {
            throw new RuntimeException('Rolul intern necesar contului protejat nu există.');
        }

        $protectedUserId = (int) $protectedUsers->sole();

        DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->where('model_type', User::class)
            ->where('model_id', '!=', $protectedUserId)
            ->delete();

        DB::table('model_has_roles')->insertOrIgnore([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $protectedUserId,
        ]);
    }
};
