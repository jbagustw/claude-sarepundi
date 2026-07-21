<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('user');
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->hasRole('user') && $booking->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('user');
    }

    /**
     * Whether this mitra owns the villa the booking is for, and so may
     * accept/reject it.
     */
    public function respondAsMitra(User $user, Booking $booking): bool
    {
        return $user->hasRole('mitra')
            && $booking->villa->mitraProfile?->user_id === $user->id;
    }

    /**
     * A booking is only cancellable by its owner while it's still awaiting
     * a mitra decision or already confirmed — not before payment (nothing
     * to cancel yet) and not once it's already resolved, checked in, or
     * finished.
     */
    public function cancel(User $user, Booking $booking): bool
    {
        return $user->hasRole('user')
            && $booking->user_id === $user->id
            && in_array($booking->status, ['menunggu_konfirmasi', 'dikonfirmasi'], true);
    }
}
