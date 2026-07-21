<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Villa;
use App\Services\VillaAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Booking::class);

        $bookings = $request->user()->bookings()
            ->with(['villa.images', 'latestPayment'])
            ->latest()
            ->get();

        return BookingResource::collection($bookings);
    }

    public function show(Booking $booking)
    {
        $this->authorize('view', $booking);

        return new BookingResource($booking->load(['villa.images', 'latestPayment']));
    }

    public function store(StoreBookingRequest $request, VillaAvailabilityService $service)
    {
        $data = $request->validated();

        $villa = Villa::publiclyVisible()->findOrFail($data['villa_id']);

        $checkIn = CarbonImmutable::parse($data['check_in_date']);
        $checkOut = CarbonImmutable::parse($data['check_out_date']);

        $result = $service->evaluate($villa, $checkIn, $checkOut, $data['guest_count']);

        if (! $result['available']) {
            throw ValidationException::withMessages(['check_in_date' => [$result['reason']]]);
        }

        $booking = Booking::create([
            'booking_code' => $this->generateBookingCode(),
            'user_id' => $request->user()->id,
            'villa_id' => $villa->id,
            'check_in_date' => $data['check_in_date'],
            'check_out_date' => $data['check_out_date'],
            'guest_count' => $data['guest_count'],
            'total_price' => $result['total_price'],
            'commission_amount' => $result['commission_amount'],
            'mitra_payout_amount' => $result['mitra_payout_amount'],
            'status' => 'pending_payment',
        ]);

        return (new BookingResource($booking->load('villa.images')))
            ->response()
            ->setStatusCode(201);
    }

    private function generateBookingCode(): string
    {
        do {
            $code = 'BK'.now()->format('Ymd').Str::upper(Str::random(4));
        } while (Booking::where('booking_code', $code)->exists());

        return $code;
    }
}
