<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Glamping;
use App\Services\GlampingAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class GlampingAvailabilityController extends Controller
{
    public function __invoke(Request $request, string $slug, GlampingAvailabilityService $service)
    {
        $glamping = Glamping::publiclyVisible()->where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'guest_count' => ['nullable', 'integer', 'min:1'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ]);

        $result = $service->evaluate(
            $glamping,
            CarbonImmutable::parse($data['check_in_date']),
            CarbonImmutable::parse($data['check_out_date']),
            $data['guest_count'] ?? 1,
            $data['coupon_code'] ?? null,
        );

        return response()->json(['data' => $result]);
    }
}
