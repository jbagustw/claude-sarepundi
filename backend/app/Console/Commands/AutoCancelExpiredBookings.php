<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingCancellationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:auto-cancel-expired')]
#[Description('Auto-cancel bookings whose mitra_confirmation_deadline has passed without a mitra decision, and refund the user 100% (CLAUDE.md).')]
class AutoCancelExpiredBookings extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BookingCancellationService $cancellationService): void
    {
        $expired = Booking::where('status', 'menunggu_konfirmasi')
            ->where('mitra_confirmation_deadline', '<', now())
            ->get();

        foreach ($expired as $booking) {
            $cancellationService->cancelByMitra($booking, 'mitra_timeout');
            $this->info("Auto-cancelled booking {$booking->booking_code} (mitra timeout).");
        }

        $this->info("Processed {$expired->count()} expired booking(s).");
    }
}
