<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminBookingResource;
use App\Models\Booking;
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
            'search' => ['sometimes', 'string', 'max:255'],
        ]);

        $bookings = Booking::with(['bookable.mitraProfile', 'user', 'latestPayment'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->query('search');
                $q->where(function ($q) use ($search) {
                    $q->where('booking_code', 'like', "%{$search}%")
                        ->orWhereHasMorph('bookable', Booking::bookableTypes(), fn ($bq) => $bq->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(20);

        return AdminBookingResource::collection($bookings);
    }
}
