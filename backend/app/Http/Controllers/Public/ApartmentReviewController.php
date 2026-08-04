<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Apartment;

class ApartmentReviewController extends Controller
{
    public function index(string $slug)
    {
        $apartment = Apartment::publiclyVisible()->where('slug', $slug)->firstOrFail();

        $reviews = $apartment->reviews()->with('user')->latest()->paginate(10);

        return ReviewResource::collection($reviews);
    }
}
