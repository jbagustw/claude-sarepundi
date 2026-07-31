<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['gathering_venue_id', 'name', 'start_time', 'end_time', 'price', 'is_active'])]
class GatheringVenueSlot extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function gatheringVenue(): BelongsTo
    {
        return $this->belongsTo(GatheringVenue::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'gathering_venue_slot_id');
    }
}
