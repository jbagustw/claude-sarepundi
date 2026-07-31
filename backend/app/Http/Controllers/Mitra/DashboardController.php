<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Homestay;
use App\Models\Transport;
use App\Models\Villa;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Statuses that represent a real, committed stay — used both for the
     * "confirmed" booking count and for the occupancy calculation. Pending
     * payment/confirmation or cancelled bookings don't occupy the listing.
     */
    private const OCCUPYING_STATUSES = ['dikonfirmasi', 'checked_in', 'selesai'];

    public function stats(Request $request)
    {
        $mitraProfile = $request->user()->mitraProfile;

        $bookingCounts = Booking::forMitra($mitraProfile)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Payout only actually lands once a stay is complete (see the
        // payout trigger design for milestone 9), so "pendapatan" here
        // means realized earnings, not money still in flight.
        $totalPendapatan = (int) Booking::forMitra($mitraProfile)
            ->where('status', 'selesai')
            ->sum('mitra_payout_amount');

        $publishedVillaCount = $mitraProfile->villas()->where('status', 'published')->count();
        $publishedHomestayCount = $mitraProfile->homestays()->where('status', 'published')->count();
        $publishedTransportCount = $mitraProfile->transports()->where('status', 'published')->count();

        return response()->json(['data' => [
            'total_pendapatan' => $totalPendapatan,
            'booking_counts' => $bookingCounts,
            'total_villas' => $mitraProfile->villas()->count(),
            'published_villas' => $publishedVillaCount,
            'total_homestays' => $mitraProfile->homestays()->count(),
            'published_homestays' => $publishedHomestayCount,
            'total_gathering_venues' => $mitraProfile->gatheringVenues()->count(),
            'published_gathering_venues' => $mitraProfile->gatheringVenues()->where('status', 'published')->count(),
            'total_transports' => $mitraProfile->transports()->count(),
            'published_transports' => $publishedTransportCount,
            // Occupancy is a nights/days-booked metric, which fits Villa,
            // Homestay, and Transport (all date-range rentals) but not
            // gathering venues (per-slot, multiple bookings/day possible).
            'occupancy_rate' => $this->occupancyRateThisMonth(
                $mitraProfile,
                $publishedVillaCount + $publishedHomestayCount + $publishedTransportCount
            ),
        ]]);
    }

    /**
     * % of this month's listing-nights (villa + homestay + transport
     * combined) that are booked (confirmed or beyond), across all of this
     * mitra's published listings. Nights from bookings that only partially
     * overlap the month are clipped to it.
     */
    private function occupancyRateThisMonth($mitraProfile, int $publishedListingCount): float
    {
        if ($publishedListingCount === 0) {
            return 0.0;
        }

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $bookedNights = Booking::forMitra($mitraProfile)
            ->whereIn('bookable_type', [Villa::class, Homestay::class, Transport::class])
            ->whereIn('status', self::OCCUPYING_STATUSES)
            ->where('check_in_date', '<', $monthEnd)
            ->where('check_out_date', '>', $monthStart)
            ->get(['check_in_date', 'check_out_date'])
            ->sum(function (Booking $booking) use ($monthStart, $monthEnd) {
                $start = $booking->check_in_date->max($monthStart);
                $end = $booking->check_out_date->min($monthEnd->copy()->addDay());

                return max(0, $start->diffInDays($end));
            });

        $totalListingNights = $publishedListingCount * $monthStart->daysInMonth;

        return round($bookedNights / $totalListingNights * 100, 1);
    }
}
