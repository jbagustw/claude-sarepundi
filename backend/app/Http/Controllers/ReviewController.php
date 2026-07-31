<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Booking;
use App\Models\Review;
use App\Services\NotificationService;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, Booking $booking, NotificationService $notifications)
    {
        $this->authorize('view', $booking);

        abort_unless($booking->status === 'selesai', 422, 'Review hanya bisa diberikan setelah booking selesai.');
        abort_if($booking->review()->exists(), 422, 'Booking ini sudah direview.');

        $review = Review::create([
            ...$request->validated(),
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'reviewable_type' => $booking->bookable_type,
            'reviewable_id' => $booking->bookable_id,
        ]);

        $mitraUser = $booking->bookable->mitraProfile->user;
        $notifications->notify(
            $mitraUser,
            'new_review',
            'Review baru untuk villa kamu',
            "{$booking->user->name} memberi rating {$review->rating}/5 untuk {$booking->bookable->name}."
        );

        return (new ReviewResource($review))->response()->setStatusCode(201);
    }
}
