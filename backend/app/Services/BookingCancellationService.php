<?php

namespace App\Services;

use App\Exceptions\XenditRequestException;
use App\Models\Booking;
use App\Models\Refund;
use App\Services\Xendit\XenditService;
use Illuminate\Support\Facades\Log;

class BookingCancellationService
{
    public function __construct(
        private readonly XenditService $xendit,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * User-initiated cancellation — the only kind there is (mitra never
     * approves/rejects a booking, so there's no mitra-side cancellation
     * path). Per CLAUDE.md's H-2 rule: >= 2 days before check-in, 85%
     * refund; otherwise 0%. The caller must check BookingPolicy::cancel
     * (status must be dikonfirmasi) before calling this.
     */
    public function cancelByUser(Booking $booking): void
    {
        $percentage = $this->confirmedRefundPercentage($booking);
        $reason = 'user_cancel_confirmed';

        $amount = (int) round($booking->total_price * $percentage / 100);

        $booking->update([
            'status' => 'dibatalkan_user',
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
            'refund_percentage' => $percentage,
            'refund_amount' => $amount,
        ]);

        $this->refundIfOwed($booking, $amount, $reason);

        $mitraUser = $booking->bookable->mitraProfile->user;
        $this->notifications->notify(
            $mitraUser,
            'booking_cancelled_by_user',
            'Booking dibatalkan user',
            "Booking {$booking->booking_code} untuk {$booking->bookable->name} dibatalkan oleh user (refund {$percentage}%)."
        );
    }

    /**
     * >= H-2 (2 or more days before check-in) -> 85%, otherwise 0%.
     */
    private function confirmedRefundPercentage(Booking $booking): int
    {
        $daysUntilCheckIn = now()->startOfDay()->diffInDays($booking->check_in_date, false);

        return $daysUntilCheckIn >= 2 ? 85 : 0;
    }

    private function refundIfOwed(Booking $booking, int $amount, string $reason): void
    {
        if ($amount <= 0) {
            return;
        }

        $payment = $booking->payments()->where('status', 'success')->latest()->first();

        if (! $payment) {
            // Shouldn't happen (a cancellable status implies a successful
            // payment got us there), but there's nothing to refund if so.
            return;
        }

        try {
            $result = $this->xendit->createRefund($payment, $amount, $reason);

            Refund::create([
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'percentage' => $booking->refund_percentage,
                'reason' => $reason,
                'xendit_refund_id' => $result['refund_id'],
                'status' => $result['status'] === 'succeeded' ? 'succeeded' : 'pending',
                'processed_at' => now(),
            ]);

            if ($result['status'] === 'succeeded') {
                $payment->update(['status' => $amount === $payment->amount ? 'refunded' : 'partial_refunded']);
            }
        } catch (XenditRequestException $e) {
            // The booking cancellation itself must not be blocked by a
            // refund API failure (missing config, Xendit outage, etc.) —
            // record the failure for an admin to follow up on manually
            // instead of failing silently.
            Log::error('Refund failed during booking cancellation', [
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            Refund::create([
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'percentage' => $booking->refund_percentage,
                'reason' => $reason,
                'status' => 'failed',
            ]);
        }
    }
}
