<?php

namespace App\Http\Controllers;

use App\Exceptions\XenditRequestException;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Xendit\XenditService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function store(Request $request, Booking $booking, XenditService $xendit)
    {
        $this->authorize('view', $booking);

        abort_unless($booking->status === 'pending_payment', 422, 'Booking ini tidak sedang menunggu pembayaran.');

        $pending = $booking->payments()->where('status', 'pending')->latest()->first();

        if ($pending && $pending->invoice_url) {
            return response()->json(['data' => ['invoice_url' => $pending->invoice_url]]);
        }

        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        try {
            $invoice = $xendit->createInvoice(
                $booking,
                successUrl: "{$frontendUrl}/user/bookings/{$booking->id}?payment=success",
                failureUrl: "{$frontendUrl}/user/bookings/{$booking->id}?payment=failed",
            );
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
}
