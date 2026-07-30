<?php

namespace App\Policies;

use App\Models\Homestay;
use App\Models\User;

class HomestayPolicy
{
    protected function owns(User $user, Homestay $homestay): bool
    {
        return $user->hasRole('mitra')
            && $homestay->mitraProfile?->user_id === $user->id;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('mitra');
    }

    public function view(User $user, Homestay $homestay): bool
    {
        return $this->owns($user, $homestay);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('mitra');
    }

    public function update(User $user, Homestay $homestay): bool
    {
        return $this->owns($user, $homestay);
    }

    public function delete(User $user, Homestay $homestay): bool
    {
        return $this->owns($user, $homestay);
    }

    /**
     * Move a draft/rejected homestay into pending_review. Requires the
     * mitra account itself to already be approved (see VillaPolicy::submit
     * for the same rule applied to villas).
     */
    public function submit(User $user, Homestay $homestay): bool
    {
        return $this->owns($user, $homestay)
            && $homestay->mitraProfile->status === 'approved'
            && in_array($homestay->status, ['draft', 'rejected'], true);
    }
}
