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

// Advances confirmed bookings through checked_in -> selesai as their
// dates pass — this is what feeds the payout run below.
Schedule::command('bookings:advance-completed-stays')->daily();

// CLAUDE.md: payout dicairkan sesuai jadwal (misal tiap tanggal 1 & 15).
Schedule::command('payouts:run')->cron('0 2 1,15 * *');
