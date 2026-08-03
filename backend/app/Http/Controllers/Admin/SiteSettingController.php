<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteSettingRequest;
use App\Http\Requests\Admin\UploadHeroImageRequest;
use App\Http\Requests\Admin\UploadFaviconRequest;
use App\Http\Requests\Admin\UploadLogoRequest;
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

    public function uploadLogo(UploadLogoRequest $request): SiteSettingResource
    {
        $setting = SiteSetting::current();

        if ($setting->logo_path) {
            Storage::disk('public')->delete($setting->logo_path);
        }

        $path = $request->file('image')->store('logo', 'public');

        $setting->update(['logo_path' => $path]);

        return new SiteSettingResource($setting);
    }

    public function destroyLogo(): SiteSettingResource
    {
        $setting = SiteSetting::current();

        if ($setting->logo_path) {
            Storage::disk('public')->delete($setting->logo_path);
            $setting->update(['logo_path' => null]);
        }

        return new SiteSettingResource($setting);
    }

    public function uploadFavicon(UploadFaviconRequest $request): SiteSettingResource
    {
        $setting = SiteSetting::current();

        if ($setting->favicon_path) {
            Storage::disk('public')->delete($setting->favicon_path);
        }

        $path = $request->file('image')->store('favicon', 'public');

        $setting->update(['favicon_path' => $path]);

        return new SiteSettingResource($setting);
    }

    public function destroyFavicon(): SiteSettingResource
    {
        $setting = SiteSetting::current();

        if ($setting->favicon_path) {
            Storage::disk('public')->delete($setting->favicon_path);
            $setting->update(['favicon_path' => null]);
        }

        return new SiteSettingResource($setting);
    }
}
