<?php

namespace App\Services;

use App\Exceptions\XenditRequestException;
use App\Models\Booking;
use App\Models\Refund;
use App\Services\Xendit\XenditService;
use Illuminate\Support\Facades\Log;

class BookingCancellationService
{
    public function __construct(private readonly XenditService $xendit)
    {
    }

    /**
     * A mitra rejecting a booking or letting it time out are both "not the
     * user's fault" per CLAUDE.md, so both always carry a 100% refund and
     * land the booking in the same dibatalkan_mitra status — this is the
     * one place that logic lives so the two callers (the reject endpoint
     * and the auto-cancel scheduled command) can't drift apart.
     */
    public function cancelByMitra(Booking $booking, string $reason): void
    {
        $booking->update([
            'status' => 'dibatalkan_mitra',
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
            'refund_percentage' => 100,
            'refund_amount' => $booking->total_price,
        ]);

        $payment = $booking->payments()->where('status', 'success')->latest()->first();

        if (! $payment) {
            // Shouldn't happen (menunggu_konfirmasi implies a successful
            // payment got us there), but there's nothing to refund if so.
            return;
        }

        try {
            $result = $this->xendit->createRefund($payment, $booking->total_price, $reason);

            Refund::create([
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'amount' => $booking->total_price,
                'percentage' => 100,
                'reason' => $reason,
                'xendit_refund_id' => $result['refund_id'],
                'status' => $result['status'] === 'succeeded' ? 'succeeded' : 'pending',
                'processed_at' => now(),
            ]);

            if ($result['status'] === 'succeeded') {
                $payment->update(['status' => 'refunded']);
            }
        } catch (XenditRequestException $e) {
            // The booking cancellation itself must not be blocked by a
            // refund API failure (missing config, Xendit outage, etc.) —
            // record the failure for an admin to follow up on manually
            // instead of failing silently.
            Log::error('Refund failed during mitra cancellation', [
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            Refund::create([
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'amount' => $booking->total_price,
                'percentage' => 100,
                'reason' => $reason,
                'status' => 'failed',
            ]);
        }
    }
}
