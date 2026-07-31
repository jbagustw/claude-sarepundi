<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBannerRequest;
use App\Http\Requests\Admin\UpdateBannerRequest;
use App\Http\Requests\Admin\UploadBannerImageRequest;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::orderBy('sort_order')->latest()->get();

        return BannerResource::collection($banners);
    }

    public function store(StoreBannerRequest $request)
    {
        $banner = Banner::create([...$request->validated(), 'is_active' => true]);

        return (new BannerResource($banner))->response()->setStatusCode(201);
    }

    public function show(Banner $banner)
    {
        return new BannerResource($banner);
    }

    public function update(UpdateBannerRequest $request, Banner $banner)
    {
        $banner->update($request->validated());

        return new BannerResource($banner);
    }

    public function destroy(Banner $banner)
    {
        if ($banner->image_path) {
            Storage::disk('public')->delete($banner->image_path);
        }

        $banner->delete();

        return response()->json(['message' => 'Banner dihapus.']);
    }

    public function uploadImage(UploadBannerImageRequest $request, Banner $banner)
    {
        if ($banner->image_path) {
            Storage::disk('public')->delete($banner->image_path);
        }

        $path = $request->file('image')->store('banners', 'public');

        $banner->update(['image_path' => $path]);

        return new BannerResource($banner);
    }
}
