<?php

namespace App\Services;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;

/**
 * Renders the two guest-facing PDF documents every paid booking gets,
 * mirroring what an OTA like Traveloka sends: a Voucher (shown to the
 * property/mitra at check-in) and a Receipt (proof of purchase). Both the
 * download endpoints and the confirmation email build their PDFs through
 * here so the two stay byte-for-byte identical.
 */
class BookingDocumentService
{
    public function voucherPdf(Booking $booking): PdfDocument
    {
        return Pdf::loadView('documents.voucher', $this->context($booking));
    }

    public function receiptPdf(Booking $booking): PdfDocument
    {
        return Pdf::loadView('documents.receipt', $this->context($booking));
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Booking $booking): array
    {
        $booking->loadMissing(['user', 'coupon', 'slot', 'latestPayment', 'bookable.mitraProfile.user']);
        $type = $booking->bookableType();

        return [
            'booking' => $booking,
            'type' => $type,
            'typeLabel' => match ($type) {
                'gathering_venue' => 'Lokasi Gathering',
                'transport' => 'Transport',
                'glamping' => 'Glamping',
                'homestay' => 'Homestay',
                default => 'Villa',
            },
        ];
    }
}
