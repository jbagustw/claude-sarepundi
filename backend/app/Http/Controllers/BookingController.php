<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Homestay;
use App\Models\Villa;
use App\Services\BookingCancellationService;
use App\Services\HomestayAvailabilityService;
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
            ->with(['bookable.images', 'latestPayment'])
            ->latest()
            ->get();

        return BookingResource::collection($bookings);
    }

    public function show(Booking $booking)
    {
        $this->authorize('view', $booking);

        return new BookingResource($booking->load(['bookable.images', 'latestPayment', 'review']));
    }

    public function store(StoreBookingRequest $request, VillaAvailabilityService $villaService, HomestayAvailabilityService $homestayService)
    {
        $data = $request->validated();

        if ($data['bookable_type'] === 'homestay') {
            $bookable = Homestay::publiclyVisible()->findOrFail($data['bookable_id']);
            $result = $homestayService->evaluate(
                $bookable,
                CarbonImmutable::parse($data['check_in_date']),
                CarbonImmutable::parse($data['check_out_date']),
                $data['guest_count'],
            );
        } else {
            $bookable = Villa::publiclyVisible()->findOrFail($data['bookable_id']);
            $result = $villaService->evaluate(
                $bookable,
                CarbonImmutable::parse($data['check_in_date']),
                CarbonImmutable::parse($data['check_out_date']),
                $data['guest_count'],
            );
        }

        if (! $result['available']) {
            throw ValidationException::withMessages(['check_in_date' => [$result['reason']]]);
        }

        $booking = Booking::create([
            'booking_code' => $this->generateBookingCode(),
            'user_id' => $request->user()->id,
            'bookable_type' => $bookable::class,
            'bookable_id' => $bookable->id,
            'check_in_date' => $data['check_in_date'],
            'check_out_date' => $data['check_out_date'],
            'guest_count' => $data['guest_count'],
            'total_price' => $result['total_price'],
            'commission_amount' => $result['commission_amount'],
            'mitra_payout_amount' => $result['mitra_payout_amount'],
            'status' => 'pending_payment',
        ]);

        return (new BookingResource($booking->load('bookable.images')))
            ->response()
            ->setStatusCode(201);
    }

    public function cancel(Booking $booking, BookingCancellationService $cancellationService)
    {
        $this->authorize('cancel', $booking);

        $cancellationService->cancelByUser($booking);

        return new BookingResource($booking->fresh()->load(['bookable.images', 'latestPayment']));
    }

    private function generateBookingCode(): string
    {
        do {
            $code = 'BK'.now()->format('Ymd').Str::upper(Str::random(4));
        } while (Booking::where('booking_code', $code)->exists());

        return $code;
    }
}
