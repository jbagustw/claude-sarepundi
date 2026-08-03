<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Resources\MitraBookingResource;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['sometimes', Rule::in([
                'pending_payment', 'dikonfirmasi',
                'dibatalkan_user', 'checked_in', 'selesai',
            ])],
        ]);

        $bookings = Booking::forMitra($request->user()->mitraProfile)
            ->with(['bookable', 'slot', 'user'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest()
            ->get();

        return MitraBookingResource::collection($bookings);
    }
}
