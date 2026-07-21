<?php

namespace App\Services\Xendit;

use App\Exceptions\XenditRequestException;
use App\Models\Booking;
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
