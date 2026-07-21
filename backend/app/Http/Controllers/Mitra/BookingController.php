<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Resources\MitraBookingResource;
use App\Models\Booking;
use App\Services\BookingCancellationService;
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

        $bookings = Booking::whereHas(
            'villa',
            fn ($q) => $q->where('mitra_id', $request->user()->mitraProfile->id)
        )
            ->with(['villa', 'user'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest()
            ->get();

        return MitraBookingResource::collection($bookings);
    }

    public function accept(Booking $booking)
    {
        $this->authorize('respondAsMitra', $booking);

        abort_unless($booking->status === 'menunggu_konfirmasi', 422, 'Booking ini tidak sedang menunggu konfirmasi.');

        $booking->update([
            'status' => 'dikonfirmasi',
            'mitra_confirmed_at' => now(),
        ]);

        return new MitraBookingResource($booking->load(['villa', 'user']));
    }

    public function reject(Booking $booking, BookingCancellationService $cancellationService)
    {
        $this->authorize('respondAsMitra', $booking);

        abort_unless($booking->status === 'menunggu_konfirmasi', 422, 'Booking ini tidak sedang menunggu konfirmasi.');

        $cancellationService->cancelByMitra($booking, 'mitra_reject');

        return new MitraBookingResource($booking->fresh()->load(['villa', 'user']));
    }
}
