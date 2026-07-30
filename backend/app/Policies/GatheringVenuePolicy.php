<?php

namespace App\Policies;

use App\Models\GatheringVenue;
use App\Models\User;

class GatheringVenuePolicy
{
    protected function owns(User $user, GatheringVenue $venue): bool
    {
        return $user->hasRole('mitra')
            && $venue->mitraProfile?->user_id === $user->id;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('mitra');
    }

    public function view(User $user, GatheringVenue $venue): bool
    {
        return $this->owns($user, $venue);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('mitra');
    }

    public function update(User $user, GatheringVenue $venue): bool
    {
        return $this->owns($user, $venue);
    }

    public function delete(User $user, GatheringVenue $venue): bool
    {
        return $this->owns($user, $venue);
    }

    /**
     * Move a draft/rejected venue into pending_review. Requires the mitra
     * account itself to already be approved (see VillaPolicy::submit for
     * the same rule applied to villas).
     */
    public function submit(User $user, GatheringVenue $venue): bool
    {
        return $this->owns($user, $venue)
            && $venue->mitraProfile->status === 'approved'
            && in_array($venue->status, ['draft', 'rejected'], true);
    }
}
