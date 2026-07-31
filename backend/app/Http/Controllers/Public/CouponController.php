<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;

class CouponController extends Controller
{
    public function index()
    {
        $coupons = Coupon::visible()->orderBy('sort_order')->latest()->get();

        return CouponResource::collection($coupons);
    }
}
