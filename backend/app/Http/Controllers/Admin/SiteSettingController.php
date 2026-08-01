<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteSettingRequest;
use App\Http\Resources\SiteSettingResource;
use App\Models\SiteSetting;

class SiteSettingController extends Controller
{
    public function show(): SiteSettingResource
    {
        return new SiteSettingResource(SiteSetting::current());
    }

    public function update(UpdateSiteSettingRequest $request): SiteSettingResource
    {
        $setting = SiteSetting::current();
        $setting->update($request->validated());

        return new SiteSettingResource($setting);
    }
}
