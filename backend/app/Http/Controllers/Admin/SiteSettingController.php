<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteSettingRequest;
use App\Http\Requests\Admin\UploadHeroImageRequest;
use App\Http\Resources\SiteSettingResource;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;

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

    public function uploadHeroImage(UploadHeroImageRequest $request): SiteSettingResource
    {
        $setting = SiteSetting::current();

        if ($setting->hero_image_path) {
            Storage::disk('public')->delete($setting->hero_image_path);
        }

        $path = $request->file('image')->store('hero', 'public');

        $setting->update(['hero_image_path' => $path]);

        return new SiteSettingResource($setting);
    }

    public function destroyHeroImage(): SiteSettingResource
    {
        $setting = SiteSetting::current();

        if ($setting->hero_image_path) {
            Storage::disk('public')->delete($setting->hero_image_path);
            $setting->update(['hero_image_path' => null]);
        }

        return new SiteSettingResource($setting);
    }
}
