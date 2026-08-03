<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'instagram_url',
    'facebook_url',
    'tiktok_url',
    'hero_image_path',
    'logo_path',
    'favicon_path',
])]
class SiteSetting extends Model
{
    /**
     * There is only ever one row — settings are site-wide, not per-record.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
