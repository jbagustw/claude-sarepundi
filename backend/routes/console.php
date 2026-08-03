<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Advances confirmed bookings through checked_in -> selesai as their
// dates pass — this is what feeds the payout run below.
Schedule::command('bookings:advance-completed-stays')->daily();

// CLAUDE.md: payout dicairkan sesuai jadwal (misal tiap tanggal 1 & 15).
Schedule::command('payouts:run')->cron('0 2 1,15 * *');

// PRD: reminder H-1 sebelum check-in.
Schedule::command('bookings:send-checkin-reminders')->dailyAt('09:00');
