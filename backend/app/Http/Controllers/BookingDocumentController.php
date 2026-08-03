<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\BookingDocumentService;

class BookingDocumentController extends Controller
{
    public function __construct(private readonly BookingDocumentService $documents) {}

    public function voucher(Booking $booking)
    {
        $this->authorize('viewDocument', $booking);

        return $this->documents->voucherPdf($booking)
            ->download("Voucher-{$booking->booking_code}.pdf");
    }

    public function receipt(Booking $booking)
    {
        $this->authorize('viewDocument', $booking);

        return $this->documents->receiptPdf($booking)
            ->download("Receipt-{$booking->booking_code}.pdf");
    }
}
