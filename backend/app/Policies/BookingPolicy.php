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

    /**
     * Voucher/receipt PDFs are only meaningful once a payment has actually
     * gone through — a pending_payment booking has nothing to prove yet.
     * Accessible by the guest who booked it, the mitra whose listing it's
     * for (they need the voucher info to recognize the guest at check-in),
     * and admin (for transaction verification).
     */
    public function viewDocument(User $user, Booking $booking): bool
    {
        if ($booking->status === 'pending_payment') {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('user')) {
            return $booking->user_id === $user->id;
        }

        if ($user->hasRole('mitra')) {
            $mitraProfile = $user->mitraProfile;

            return $mitraProfile !== null
                && $booking->bookable?->mitra_id === $mitraProfile->id;
        }

        return false;
    }
}
