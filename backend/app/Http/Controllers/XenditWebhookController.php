<?php

namespace App\Http\Controllers;

use App\Mail\BookingPaymentConfirmed;
use App\Models\Payment;
use App\Services\NotificationService;
use App\Services\Xendit\XenditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class XenditWebhookController extends Controller
{
    public function handle(Request $request, XenditService $xendit, NotificationService $notifications)
    {
        if (! $xendit->isValidCallbackToken($request->header('x-callback-token'))) {
            return response()->json(['message' => 'Invalid callback token.'], 401);
        }

        $invoiceId = $request->input('id');
        $status = $request->input('status');

        $payment = Payment::where('xendit_invoice_id', $invoiceId)->first();

        if (! $payment) {
            Log::warning('Xendit webhook for unknown invoice', ['invoice_id' => $invoiceId]);

            return response()->json(['message' => 'Payment not found.'], 200);
        }

        // Already processed — Xendit retries webhooks, so this must be a no-op.
        if ($payment->status === 'success') {
            return response()->json(['message' => 'Already processed.']);
        }

        if ($status === 'PAID') {
            DB::transaction(function () use ($request, $payment) {
                $payment->update([
                    'status' => 'success',
                    'payment_method' => $request->input('payment_method'),
                    'xendit_payment_id' => $request->input('payment_id') ?? $request->input('id'),
                    'paid_at' => $request->input('paid_at') ?? now(),
                ]);

                // Mitra no longer approves/rejects per booking — availability
                // they've already posted is the commitment, so a successful
                // payment confirms the booking immediately.
                $payment->booking->update([
                    'status' => 'dikonfirmasi',
                    'mitra_confirmed_at' => now(),
                ]);
            });

            $booking = $payment->booking()->with(['user', 'bookable.mitraProfile.user'])->first();

            $notifications->notify(
                $booking->user,
                'payment_success',
                'Pembayaran berhasil',
                "Pembayaran untuk booking {$booking->booking_code} berhasil dan booking kamu sudah dikonfirmasi.",
                sendEmail: false,
            );

            try {
                Mail::to($booking->user->email)->send(new BookingPaymentConfirmed($booking));
            } catch (\Throwable $e) {
                // Same reasoning as NotificationService::sendEmail() — the
                // in-app notification above already landed and the
                // voucher/receipt PDFs stay downloadable from the dashboard
                // regardless, so a mail transport hiccup here shouldn't
                // fail the webhook.
                Log::warning('Failed to send booking confirmation email', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $notifications->notify(
                $booking->bookable->mitraProfile->user,
                'booking_confirmed',
                'Booking baru dikonfirmasi',
                "Booking {$booking->booking_code} untuk {$booking->bookable->name} sudah dibayar dan otomatis dikonfirmasi."
            );
        } elseif (in_array($status, ['EXPIRED', 'FAILED'], true)) {
            $payment->update(['status' => 'failed']);
        }

        return response()->json(['message' => 'OK']);
    }
}
