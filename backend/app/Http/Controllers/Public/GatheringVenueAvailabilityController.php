<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\GatheringVenue;
use App\Services\GatheringVenueAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class GatheringVenueAvailabilityController extends Controller
{
    public function __invoke(Request $request, string $slug, GatheringVenueAvailabilityService $service)
    {
        $venue = GatheringVenue::publiclyVisible()->where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $result = $service->evaluateSlotsForDate($venue, CarbonImmutable::parse($data['date']));

        return response()->json(['data' => $result]);
    }
}
