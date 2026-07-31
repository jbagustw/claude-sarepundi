<?php

namespace App\Policies;

use App\Models\Transport;
use App\Models\User;

class TransportPolicy
{
    protected function owns(User $user, Transport $transport): bool
    {
        return $user->hasRole('mitra')
            && $transport->mitraProfile?->user_id === $user->id;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('mitra');
    }

    public function view(User $user, Transport $transport): bool
    {
        return $this->owns($user, $transport);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('mitra');
    }

    public function update(User $user, Transport $transport): bool
    {
        return $this->owns($user, $transport);
    }

    public function delete(User $user, Transport $transport): bool
    {
        return $this->owns($user, $transport);
    }

    /**
     * Move a draft/rejected transport listing into pending_review.
     * Requires the mitra account itself to already be approved (see
     * VillaPolicy::submit for the same rule applied to villas).
     */
    public function submit(User $user, Transport $transport): bool
    {
        return $this->owns($user, $transport)
            && $transport->mitraProfile->status === 'approved'
            && in_array($transport->status, ['draft', 'rejected'], true);
    }
}
