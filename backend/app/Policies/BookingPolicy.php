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
}
