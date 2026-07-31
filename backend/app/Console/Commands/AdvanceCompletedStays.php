<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\NotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Nothing else in the app ever moves a booking past "dikonfirmasi" — the
 * PRD's alur (H-1 reminder -> check-in -> check-out -> selesai) implies
 * this happens automatically as dates pass, and milestone 9's payout
 * trigger depends entirely on bookings reaching "selesai".
 */
#[Signature('bookings:advance-completed-stays')]
#[Description('Move dikonfirmasi bookings to checked_in once check-in passes, and to selesai once check-out passes.')]
class AdvanceCompletedStays extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notifications): void
    {
        $checkedIn = Booking::where('status', 'dikonfirmasi')
            ->where('check_in_date', '<=', now())
            ->update(['status' => 'checked_in']);

        $this->info("Marked {$checkedIn} booking(s) as checked_in.");

        $completedBookings = Booking::whereIn('status', ['dikonfirmasi', 'checked_in'])
            ->where('check_out_date', '<=', now())
            ->with(['user', 'bookable'])
            ->get();

        foreach ($completedBookings as $booking) {
            $booking->update(['status' => 'selesai']);

            $notifications->notify(
                $booking->user,
                'booking_completed',
                'Terima kasih sudah menginap!',
                "Booking {$booking->booking_code} di {$booking->bookable->name} sudah selesai. Yuk beri review untuk villa/homestay ini."
            );
        }

        $this->info("Marked {$completedBookings->count()} booking(s) as selesai.");
    }
}
