<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Scheduler applicativo (Laravel 12).
 *
 * Qui definiamo i comandi da eseguire; sul server servirà un SOLO cron
 * che lancia "php artisan schedule:run" ogni minuto.
 */
Schedule::command('assignments:activate-scheduled')
    ->everyMinute()
    /**
     * Evita esecuzioni concorrenti se un run impiega troppo tempo.
     * Nota: richiede un cache driver che supporti atomic locks (es. redis/database).
     */
    // ->withoutOverlapping()
    ->description('Attiva assegnazioni scheduled e aggiorna default_pickup_location_id quando diventano active.');

Schedule::command('assignments:close-expired')
    ->everyMinute()
    // ->withoutOverlapping()
    ->description('Chiude assegnazioni active con end_at passato (status=ended).');