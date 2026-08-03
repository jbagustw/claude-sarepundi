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
     * A booking is only cancellable by its owner while it's confirmed —
     * not before payment (nothing to cancel yet) and not once it's already
     * resolved, checked in, or finished. Mitra never approves/rejects a
     * booking (posted availability is their commitment), so cancellation is
     * user-initiated only.
     */
    public function cancel(User $user, Booking $booking): bool
    {
        return $user->hasRole('user')
            && $booking->user_id === $user->id
            && $booking->status === 'dikonfirmasi';
    }
}
