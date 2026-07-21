<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayoutResource;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function index(Request $request)
    {
        $payouts = $request->user()->mitraProfile
            ->payouts()
            ->withCount('bookings')
            ->latest()
            ->get();

        return PayoutResource::collection($payouts);
    }
}
