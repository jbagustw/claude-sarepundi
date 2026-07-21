<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// CLAUDE.md: check every 15-30 minutes for bookings a mitra never
// responded to within the 24-hour confirmation window.
Schedule::command('bookings:auto-cancel-expired')->everyFifteenMinutes();
