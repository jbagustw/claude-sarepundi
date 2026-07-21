<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Villa;

class ReviewController extends Controller
{
    public function index(string $slug)
    {
        $villa = Villa::publiclyVisible()->where('slug', $slug)->firstOrFail();

        $reviews = $villa->reviews()->with('user')->latest()->paginate(10);

        return ReviewResource::collection($reviews);
    }
}
