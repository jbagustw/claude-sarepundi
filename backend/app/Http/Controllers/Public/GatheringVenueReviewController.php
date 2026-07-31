<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\GatheringVenue;

class GatheringVenueReviewController extends Controller
{
    public function index(string $slug)
    {
        $venue = GatheringVenue::publiclyVisible()->where('slug', $slug)->firstOrFail();

        $reviews = $venue->reviews()->with('user')->latest()->paginate(10);

        return ReviewResource::collection($reviews);
    }
}
