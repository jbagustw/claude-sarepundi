<?php

namespace App\Console\Commands;

use App\Models\Booking;
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
    public function handle(): void
    {
        $checkedIn = Booking::where('status', 'dikonfirmasi')
            ->where('check_in_date', '<=', now())
            ->update(['status' => 'checked_in']);

        $this->info("Marked {$checkedIn} booking(s) as checked_in.");

        $completed = Booking::whereIn('status', ['dikonfirmasi', 'checked_in'])
            ->where('check_out_date', '<=', now())
            ->update(['status' => 'selesai']);

        $this->info("Marked {$completed} booking(s) as selesai.");
    }
}
