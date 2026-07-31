<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\NotificationService;
use App\Services\Xendit\XenditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

                $payment->booking->update([
                    'status' => 'menunggu_konfirmasi',
                    'mitra_confirmation_deadline' => now()->addHours(24),
                ]);
            });

            $booking = $payment->booking()->with(['user', 'bookable.mitraProfile.user'])->first();

            $notifications->notify(
                $booking->user,
                'payment_success',
                'Pembayaran berhasil',
                "Pembayaran untuk booking {$booking->booking_code} berhasil. Menunggu konfirmasi dari mitra dalam 24 jam."
            );
            $notifications->notify(
                $booking->bookable->mitraProfile->user,
                'booking_awaiting_confirmation',
                'Booking baru menunggu konfirmasi',
                "Booking {$booking->booking_code} untuk {$booking->bookable->name} sudah dibayar dan menunggu konfirmasi kamu dalam 24 jam."
            );
        } elseif (in_array($status, ['EXPIRED', 'FAILED'], true)) {
            $payment->update(['status' => 'failed']);
        }

        return response()->json(['message' => 'OK']);
    }
}
