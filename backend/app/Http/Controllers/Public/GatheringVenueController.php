<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\GatheringVenueResource;
use App\Models\GatheringVenue;
use Illuminate\Http\Request;

class GatheringVenueController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'facility_ids' => ['nullable', 'array'],
            'facility_ids.*' => ['integer'],
        ]);

        $venues = GatheringVenue::publiclyVisible()
            ->with(['images', 'facilities', 'slots'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->query('q').'%'))
            ->when($request->filled('city'), fn ($q) => $q->where('city', 'like', '%'.$request->query('city').'%'))
            ->when($request->filled('capacity'), fn ($q) => $q->where('capacity', '>=', $request->integer('capacity')))
            ->when($request->filled('facility_ids'), fn ($q) => $q->whereHas(
                'facilities',
                fn ($fq) => $fq->whereIn('facilities.id', $request->query('facility_ids'))
            ))
            ->latest()
            ->paginate(12);

        return GatheringVenueResource::collection($venues);
    }

    public function show(string $slug)
    {
        $venue = GatheringVenue::publiclyVisible()
            ->with(['images', 'facilities', 'slots'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('slug', $slug)
            ->firstOrFail();

        return new GatheringVenueResource($venue);
    }
}
