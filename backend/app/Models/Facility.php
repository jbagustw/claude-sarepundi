<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'icon', 'category'])]
class Facility extends Model
{
    public function villas(): BelongsToMany
    {
        return $this->belongsToMany(Villa::class, 'villa_facilities');
    }
}
