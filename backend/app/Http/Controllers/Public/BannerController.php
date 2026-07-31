<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::visible()->orderBy('sort_order')->latest()->get();

        return BannerResource::collection($banners);
    }
}
