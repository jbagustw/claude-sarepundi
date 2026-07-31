<?php

namespace App\Services;

use App\Exceptions\XenditRequestException;
use App\Models\Booking;
use App\Models\MitraProfile;
use App\Models\Payout;
use App\Services\Xendit\XenditService;
use Illuminate\Support\Facades\Log;

class PayoutService
{
    public function __construct(private readonly XenditService $xendit)
    {
    }

    /**
     * Sweep every approved mitra's un-paid-out completed bookings into a
     * Payout batch and attempt to disburse it. Called by the scheduled
     * bi-monthly run (CLAUDE.md: tiap tanggal 1 & 15) and by the admin's
     * manual "run now" action — same logic either way.
     *
     * @return array<Payout>
     */
    public function run(): array
    {
        return MitraProfile::where('status', 'approved')
            ->get()
            ->map(fn (MitraProfile $mitra) => $this->createPayoutForMitra($mitra))
            ->filter()
            ->values()
            ->all();
    }

    private function createPayoutForMitra(MitraProfile $mitra): ?Payout
    {
        $eligibleBookings = Booking::forMitra($mitra)
            ->where('status', 'selesai')
            ->whereNull('payout_id')
            ->get();

        if ($eligibleBookings->isEmpty()) {
            return null;
        }

        $payout = Payout::create([
            'mitra_id' => $mitra->id,
            'amount' => (int) $eligibleBookings->sum('mitra_payout_amount'),
            'period_start' => $eligibleBookings->min('check_out_date'),
            'period_end' => now(),
            'status' => 'pending',
        ]);

        // Reserve these bookings into this batch immediately, independent
        // of whether the disbursement call below succeeds — a failed
        // attempt is retried against this same Payout later, it must not
        // pick up a fresh (and now different) set of bookings.
        Booking::whereIn('id', $eligibleBookings->pluck('id'))->update(['payout_id' => $payout->id]);

        $this->attemptDisbursement($payout, $mitra);

        return $payout->fresh();
    }

    public function retry(Payout $payout): void
    {
        $this->attemptDisbursement($payout, $payout->mitraProfile);
    }

    private function attemptDisbursement(Payout $payout, MitraProfile $mitra): void
    {
        try {
            $result = $this->xendit->createDisbursement($mitra, $payout->amount, "payout-{$payout->id}");

            $payout->update([
                'xendit_disbursement_id' => $result['disbursement_id'],
                'status' => in_array($result['status'], ['completed', 'succeeded'], true) ? 'completed' : 'pending',
                'failure_reason' => null,
                'processed_at' => now(),
            ]);
        } catch (XenditRequestException $e) {
            // The Payout record itself (status=failed) *is* the admin
            // notification here — surfaced on the admin payouts page for
            // manual follow-up/retry, never left silent.
            Log::error('Payout disbursement failed', [
                'payout_id' => $payout->id,
                'mitra_id' => $mitra->id,
                'error' => $e->getMessage(),
            ]);

            $payout->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);
        }
    }
}
