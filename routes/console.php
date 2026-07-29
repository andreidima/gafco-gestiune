<?php

use App\Services\OperationalAlertSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('alerts:sync', function (OperationalAlertSyncService $alerts) {
    $result = $alerts->sync(force: true);

    $this->info(
        "Alerte detectate: {$result['detected']}; închise: {$result['resolved']}; notificări: {$result['notifications']}."
    );
})->purpose('Actualizează alertele operaționale și destinatarii lor');
