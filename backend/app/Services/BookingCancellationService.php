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

        $this->refundIfOwed($booking, $booking->total_price, $reason);
    }

    /**
     * User-initiated cancellation. CLAUDE.md's refund policy depends on
     * whether the mitra had already committed to the booking:
     *   - still menunggu_konfirmasi (mitra hasn't decided) -> 100% refund,
     *     the user hasn't been let down by anyone yet.
     *   - already dikonfirmasi -> H-2 rule: >= 2 days before check-in,
     *     85% refund; otherwise 0%.
     * Any other status (not yet paid, already resolved, already checked
     * in, etc.) isn't cancellable here — the caller must check via
     * BookingPolicy::cancel before calling this.
     */
    public function cancelByUser(Booking $booking): void
    {
        [$percentage, $reason] = $booking->status === 'menunggu_konfirmasi'
            ? [100, 'user_cancel_pending']
            : [$this->confirmedRefundPercentage($booking), 'user_cancel_confirmed'];

        $amount = (int) round($booking->total_price * $percentage / 100);

        $booking->update([
            'status' => 'dibatalkan_user',
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
            'refund_percentage' => $percentage,
            'refund_amount' => $amount,
        ]);

        $this->refundIfOwed($booking, $amount, $reason);
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
