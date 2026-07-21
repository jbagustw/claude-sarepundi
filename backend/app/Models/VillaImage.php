<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['villa_id', 'image_url', 'is_primary', 'sort_order'])]
class VillaImage extends Model
{
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function villa(): BelongsTo
    {
        return $this->belongsTo(Villa::class);
    }
}
