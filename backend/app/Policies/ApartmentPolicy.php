<?php

namespace App\Policies;

use App\Models\Apartment;
use App\Models\User;

class ApartmentPolicy
{
    protected function owns(User $user, Apartment $apartment): bool
    {
        return $user->hasRole('mitra')
            && $apartment->mitraProfile?->user_id === $user->id;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('mitra');
    }

    public function view(User $user, Apartment $apartment): bool
    {
        return $this->owns($user, $apartment);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('mitra');
    }

    public function update(User $user, Apartment $apartment): bool
    {
        return $this->owns($user, $apartment);
    }

    public function delete(User $user, Apartment $apartment): bool
    {
        return $this->owns($user, $apartment);
    }

    /**
     * Move a draft/rejected apartment into pending_review. Requires the
     * mitra account itself to already be approved (see VillaPolicy::submit
     * for the same rule applied to villas).
     */
    public function submit(User $user, Apartment $apartment): bool
    {
        return $this->owns($user, $apartment)
            && $apartment->mitraProfile->status === 'approved'
            && in_array($apartment->status, ['draft', 'rejected'], true);
    }
}
