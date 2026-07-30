<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['gathering_venue_id', 'image_url', 'is_primary', 'sort_order'])]
class GatheringVenueImage extends Model
{
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function gatheringVenue(): BelongsTo
    {
        return $this->belongsTo(GatheringVenue::class);
    }
}
