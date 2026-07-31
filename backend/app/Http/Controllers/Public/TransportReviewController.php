<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Transport;

class TransportReviewController extends Controller
{
    public function index(string $slug)
    {
        $transport = Transport::publiclyVisible()->where('slug', $slug)->firstOrFail();

        $reviews = $transport->reviews()->with('user')->latest()->paginate(10);

        return ReviewResource::collection($reviews);
    }
}
