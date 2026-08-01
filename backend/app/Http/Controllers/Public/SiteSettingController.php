<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\SiteSettingResource;
use App\Models\SiteSetting;

class SiteSettingController extends Controller
{
    public function show(): SiteSettingResource
    {
        return new SiteSettingResource(SiteSetting::current());
    }
}
