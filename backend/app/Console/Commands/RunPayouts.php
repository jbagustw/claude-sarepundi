<?php

namespace App\Console\Commands;

use App\Services\PayoutService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payouts:run')]
#[Description("Batch every approved mitra's completed-but-unpaid bookings into a Payout and disburse via Xendit (CLAUDE.md: tiap tanggal 1 & 15).")]
class RunPayouts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PayoutService $payoutService): void
    {
        $payouts = $payoutService->run();

        $this->info(count($payouts).' payout(s) created.');

        foreach ($payouts as $payout) {
            $this->line("  Payout #{$payout->id} — mitra {$payout->mitra_id}: Rp".number_format($payout->amount, 0, ',', '.')." [{$payout->status}]");
        }
    }
}
