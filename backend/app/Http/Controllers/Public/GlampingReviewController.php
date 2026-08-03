<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Glamping;

class GlampingReviewController extends Controller
{
    public function index(string $slug)
    {
        $glamping = Glamping::publiclyVisible()->where('slug', $slug)->firstOrFail();

        $reviews = $glamping->reviews()->with('user')->latest()->paginate(10);

        return ReviewResource::collection($reviews);
    }
}
