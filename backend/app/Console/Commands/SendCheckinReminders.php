<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\NotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:send-checkin-reminders')]
#[Description('PRD: H-1 reminder — notify users whose confirmed booking checks in tomorrow.')]
class SendCheckinReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notifications): void
    {
        $bookings = Booking::where('status', 'dikonfirmasi')
            ->whereDate('check_in_date', now()->addDay()->toDateString())
            ->with(['user', 'villa'])
            ->get();

        foreach ($bookings as $booking) {
            $notifications->notify(
                $booking->user,
                'checkin_reminder',
                'Check-in besok!',
                "Jangan lupa, check-in untuk booking {$booking->booking_code} di {$booking->villa->name} adalah besok ({$booking->check_in_date->toDateString()})."
            );
        }

        $this->info("Sent {$bookings->count()} check-in reminder(s).");
    }
}
