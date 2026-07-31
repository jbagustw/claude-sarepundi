<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Homestay;
use App\Services\HomestayAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class HomestayAvailabilityController extends Controller
{
    public function __invoke(Request $request, string $slug, HomestayAvailabilityService $service)
    {
        $homestay = Homestay::publiclyVisible()->where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'guest_count' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = $service->evaluate(
            $homestay,
            CarbonImmutable::parse($data['check_in_date']),
            CarbonImmutable::parse($data['check_out_date']),
            $data['guest_count'] ?? 1,
        );

        return response()->json(['data' => $result]);
    }
}
