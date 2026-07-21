<?php

namespace App\Services\Xendit;

use App\Exceptions\XenditRequestException;
use App\Models\Booking;
use App\Models\MitraProfile;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * All communication with Xendit is funneled through this class per
 * CLAUDE.md — controllers must never call the Xendit API directly.
 */
class XenditService
{
    private ?string $secretKey;

    private string $baseUrl;

    private ?string $callbackToken;

    public function __construct()
    {
        $this->secretKey = config('services.xendit.secret_key');
        $this->baseUrl = rtrim(config('services.xendit.base_url'), '/');
        $this->callbackToken = config('services.xendit.callback_token');
    }

    /**
     * Create a hosted Invoice for a booking. Xendit's Invoice API lets the
     * payer pick VA / e-wallet / card on Xendit's own page, so we don't
     * know the eventual payment_method until the webhook tells us.
     *
     * @return array{invoice_id: string, invoice_url: string}
     */
    public function createInvoice(Booking $booking, string $successUrl, string $failureUrl): array
    {
        $this->assertConfigured();

        $response = Http::withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->post("{$this->baseUrl}/v2/invoices", [
                'external_id' => $booking->booking_code,
                'amount' => $booking->total_price,
                'payer_email' => $booking->user->email,
                'description' => "Pembayaran booking {$booking->booking_code} - {$booking->villa->name}",
                'success_redirect_url' => $successUrl,
                'failure_redirect_url' => $failureUrl,
            ]);

        if ($response->failed()) {
            Log::error('Xendit create invoice failed', [
                'booking_id' => $booking->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new XenditRequestException('Gagal membuat invoice pembayaran. Silakan coba lagi.');
        }

        return [
            'invoice_id' => $response->json('id'),
            'invoice_url' => $response->json('invoice_url'),
        ];
    }

    /**
     * Refund a payment. Used for the always-100% refund owed when a mitra
     * rejects/times out on a booking (CLAUDE.md), and later for the
     * partial user-initiated refunds in the cancellation module.
     *
     * @return array{refund_id: ?string, status: string}
     */
    public function createRefund(Payment $payment, int $amount, string $reason): array
    {
        $this->assertConfigured();

        $response = Http::withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->post("{$this->baseUrl}/refunds", [
                'invoice_id' => $payment->xendit_invoice_id,
                'amount' => $amount,
                'reason' => 'REQUESTED_BY_CUSTOMER',
                'metadata' => ['internal_reason' => $reason],
            ]);

        if ($response->failed()) {
            Log::error('Xendit create refund failed', [
                'payment_id' => $payment->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new XenditRequestException('Gagal memproses refund.');
        }

        return [
            'refund_id' => $response->json('id'),
            'status' => strtolower($response->json('status', 'pending')),
        ];
    }

    /**
     * Disburse a mitra's accumulated payout to their bank account.
     * CLAUDE.md: never let a failed disbursement fail silently — the
     * caller (PayoutService) is responsible for recording a failed Payout
     * an admin can see and retry, this method just surfaces the error.
     *
     * @return array{disbursement_id: ?string, status: string}
     */
    public function createDisbursement(MitraProfile $mitra, int $amount, string $externalId): array
    {
        $this->assertConfigured();

        if (empty($mitra->bank_account) || empty($mitra->bank_name)) {
            throw new XenditRequestException(
                'Data rekening mitra belum lengkap. Mitra perlu melengkapi data bank di profil.'
            );
        }

        $response = Http::withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->post("{$this->baseUrl}/disbursements", [
                'external_id' => $externalId,
                'bank_code' => $mitra->bank_name,
                'account_holder_name' => $mitra->business_name,
                'account_number' => $mitra->bank_account,
                'description' => "Payout platform booking villa - {$externalId}",
                'amount' => $amount,
            ]);

        if ($response->failed()) {
            Log::error('Xendit create disbursement failed', [
                'mitra_id' => $mitra->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new XenditRequestException('Gagal memproses payout ke rekening mitra.');
        }

        return [
            'disbursement_id' => $response->json('id'),
            'status' => strtolower($response->json('status', 'pending')),
        ];
    }

    /**
     * Verify the `x-callback-token` header Xendit sends with every webhook
     * request against the token configured in the Xendit dashboard.
     */
    public function isValidCallbackToken(?string $token): bool
    {
        return $this->callbackToken !== null
            && $token !== null
            && hash_equals($this->callbackToken, $token);
    }

    private function assertConfigured(): void
    {
        if (empty($this->secretKey)) {
            throw new XenditRequestException(
                'Konfigurasi Xendit belum lengkap. Hubungi admin platform.'
            );
        }
    }
}
