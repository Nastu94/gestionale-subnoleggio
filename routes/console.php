<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Scheduler:
 * - Chiude le assegnazioni con end_at passato ma ancora status=active.
 * - withoutOverlapping evita esecuzioni concorrenti se il server è lento o schedulato più volte.
 */
Schedule::command('assignments:close-expired')
    ->everyFiveMinutes()
    ->withoutOverlapping();