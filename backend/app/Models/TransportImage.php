<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['transport_id', 'image_url', 'is_primary', 'sort_order'])]
class TransportImage extends Model
{
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function transport(): BelongsTo
    {
        return $this->belongsTo(Transport::class);
    }
}
