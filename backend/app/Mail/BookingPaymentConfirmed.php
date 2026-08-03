<?php

namespace App\Mail;

use App\Models\Booking;
use App\Services\BookingDocumentService;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent right after a Xendit payment webhook confirms a booking. Carries the
 * same Voucher + Receipt PDFs as the download endpoints (via
 * BookingDocumentService) so the emailed copies never drift from what's
 * available in the dashboard.
 */
class BookingPaymentConfirmed extends Mailable
{
    use SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Booking {$this->booking->booking_code} Dikonfirmasi - Sarepundi",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.booking-confirmed');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        // Mailable's attachments() is invoked directly by the framework
        // (no container method-injection like a controller action gets),
        // so the service is resolved explicitly here instead of type-hinted
        // as a parameter.
        $documents = app(BookingDocumentService::class);
        $booking = $this->booking;

        return [
            Attachment::fromData(fn () => $documents->voucherPdf($booking)->output(), "Voucher-{$booking->booking_code}.pdf")
                ->withMime('application/pdf'),
            Attachment::fromData(fn () => $documents->receiptPdf($booking)->output(), "Receipt-{$booking->booking_code}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
