<?php

namespace App\Http\Controllers;

use App\Exceptions\XenditRequestException;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Xendit\XenditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function store(Request $request, Booking $booking, XenditService $xendit)
    {
        $this->authorize('view', $booking);

        abort_unless($booking->status === 'pending_payment', 422, 'Booking ini tidak sedang menunggu pembayaran.');

        $data = $request->validate([
            // Which redirect target Xendit's hosted invoice should send the
            // browser/WebView back to once payment resolves. 'web' (default)
            // is unchanged pre-existing behaviour; 'mobile' is for the
            // Flutter app, which can't be redirected to a Nuxt web page —
            // see config('app.mobile_app_scheme').
            'platform' => ['sometimes', Rule::in(['web', 'mobile'])],
        ]);
        $platform = $data['platform'] ?? 'web';

        $pending = $booking->payments()->where('status', 'pending')->latest()->first();

        if ($pending && $pending->invoice_url) {
            // NOTE: reused as-is regardless of $platform — Xendit's
            // success/failure URLs are fixed at invoice creation and can't
            // be changed by a later request. A client that switches
            // platform mid-payment (rare) will still get redirected to
            // whichever platform first created this pending invoice.
            return response()->json(['data' => ['invoice_url' => $pending->invoice_url]]);
        }

        [$successUrl, $failureUrl] = $platform === 'mobile'
            ? $this->mobileRedirectUrls($booking)
            : $this->webRedirectUrls($booking);

        try {
            $invoice = $xendit->createInvoice($booking, $successUrl, $failureUrl);
        } catch (XenditRequestException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'xendit_invoice_id' => $invoice['invoice_id'],
            'invoice_url' => $invoice['invoice_url'],
            'amount' => $booking->total_price,
            'status' => 'pending',
        ]);

        return response()->json(['data' => ['invoice_url' => $payment->invoice_url]], 201);
    }

    /**
     * @return array{0: string, 1: string} [successUrl, failureUrl]
     */
    private function webRedirectUrls(Booking $booking): array
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        return [
            "{$frontendUrl}/user/bookings/{$booking->id}?payment=success",
            "{$frontendUrl}/user/bookings/{$booking->id}?payment=failed",
        ];
    }

    /**
     * Deep links back into the Flutter app. The app must register this same
     * custom URL scheme (config('app.mobile_app_scheme'), default
     * "sarepundi") on both Android (intent-filter) and iOS (CFBundleURLTypes)
     * for the OS to hand control back to it when the in-app browser/WebView
     * navigates here after payment.
     *
     * @return array{0: string, 1: string} [successUrl, failureUrl]
     */
    private function mobileRedirectUrls(Booking $booking): array
    {
        $scheme = config('app.mobile_app_scheme');

        return [
            "{$scheme}://booking/{$booking->id}?payment=success",
            "{$scheme}://booking/{$booking->id}?payment=failed",
        ];
    }
}
