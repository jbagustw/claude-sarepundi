<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['glamping_id', 'image_url', 'is_primary', 'sort_order'])]
class GlampingImage extends Model
{
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function glamping(): BelongsTo
    {
        return $this->belongsTo(Glamping::class);
    }
}
