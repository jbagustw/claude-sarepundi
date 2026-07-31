<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Resources\MitraBookingResource;
use App\Models\Booking;
use App\Services\BookingCancellationService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['sometimes', Rule::in([
                'pending_payment', 'menunggu_konfirmasi', 'dikonfirmasi',
                'dibatalkan_mitra', 'dibatalkan_user', 'checked_in', 'selesai',
            ])],
        ]);

        $bookings = Booking::forMitra($request->user()->mitraProfile)
            ->with(['bookable', 'slot', 'user'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest()
            ->get();

        return MitraBookingResource::collection($bookings);
    }

    public function accept(Booking $booking, NotificationService $notifications)
    {
        $this->authorize('respondAsMitra', $booking);

        abort_unless($booking->status === 'menunggu_konfirmasi', 422, 'Booking ini tidak sedang menunggu konfirmasi.');

        $booking->update([
            'status' => 'dikonfirmasi',
            'mitra_confirmed_at' => now(),
        ]);

        $notifications->notify(
            $booking->user,
            'booking_confirmed',
            'Booking dikonfirmasi',
            "Booking {$booking->booking_code} untuk {$booking->bookable->name} telah dikonfirmasi oleh mitra."
        );

        return new MitraBookingResource($booking->load(['bookable', 'slot', 'user']));
    }

    public function reject(Booking $booking, BookingCancellationService $cancellationService)
    {
        $this->authorize('respondAsMitra', $booking);

        abort_unless($booking->status === 'menunggu_konfirmasi', 422, 'Booking ini tidak sedang menunggu konfirmasi.');

        $cancellationService->cancelByMitra($booking, 'mitra_reject');

        return new MitraBookingResource($booking->fresh()->load(['bookable', 'slot', 'user']));
    }
}
