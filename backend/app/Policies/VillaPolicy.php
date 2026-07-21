<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Villa;

class VillaPolicy
{
    protected function owns(User $user, Villa $villa): bool
    {
        return $user->hasRole('mitra')
            && $villa->mitraProfile?->user_id === $user->id;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('mitra');
    }

    public function view(User $user, Villa $villa): bool
    {
        return $this->owns($user, $villa);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('mitra');
    }

    public function update(User $user, Villa $villa): bool
    {
        return $this->owns($user, $villa);
    }

    public function delete(User $user, Villa $villa): bool
    {
        return $this->owns($user, $villa);
    }

    /**
     * Move a draft/rejected villa into pending_review. Requires the mitra
     * account itself to already be approved — CLAUDE.md: villas from a
     * mitra that isn't approved must never reach the public catalog, so we
     * don't let them enter the review queue in the first place.
     */
    public function submit(User $user, Villa $villa): bool
    {
        return $this->owns($user, $villa)
            && $villa->mitraProfile->status === 'approved'
            && in_array($villa->status, ['draft', 'rejected'], true);
    }
}
