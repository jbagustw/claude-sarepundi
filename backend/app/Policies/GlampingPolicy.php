<?php

namespace App\Policies;

use App\Models\Glamping;
use App\Models\User;

class GlampingPolicy
{
    protected function owns(User $user, Glamping $glamping): bool
    {
        return $user->hasRole('mitra')
            && $glamping->mitraProfile?->user_id === $user->id;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('mitra');
    }

    public function view(User $user, Glamping $glamping): bool
    {
        return $this->owns($user, $glamping);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('mitra');
    }

    public function update(User $user, Glamping $glamping): bool
    {
        return $this->owns($user, $glamping);
    }

    public function delete(User $user, Glamping $glamping): bool
    {
        return $this->owns($user, $glamping);
    }

    /**
     * Move a draft/rejected glamping into pending_review. Requires the
     * mitra account itself to already be approved (see VillaPolicy::submit
     * for the same rule applied to villas).
     */
    public function submit(User $user, Glamping $glamping): bool
    {
        return $this->owns($user, $glamping)
            && $glamping->mitraProfile->status === 'approved'
            && in_array($glamping->status, ['draft', 'rejected'], true);
    }
}
