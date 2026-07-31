<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Homestay;

class HomestayReviewController extends Controller
{
    public function index(string $slug)
    {
        $homestay = Homestay::publiclyVisible()->where('slug', $slug)->firstOrFail();

        $reviews = $homestay->reviews()->with('user')->latest()->paginate(10);

        return ReviewResource::collection($reviews);
    }
}
