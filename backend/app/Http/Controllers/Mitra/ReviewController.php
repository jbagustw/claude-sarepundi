<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mitra\ReplyReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $reviews = Review::forMitra($request->user()->mitraProfile)
            ->with(['user', 'reviewable'])
            ->latest()
            ->get();

        return ReviewResource::collection($reviews);
    }

    public function reply(ReplyReviewRequest $request, Review $review)
    {
        abort_if($review->mitra_reply !== null, 422, 'Review ini sudah dibalas.');

        $review->update([
            'mitra_reply' => $request->validated('mitra_reply'),
            'mitra_replied_at' => now(),
        ]);

        return new ReviewResource($review);
    }
}
