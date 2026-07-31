<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Transport;
use App\Services\TransportAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class TransportAvailabilityController extends Controller
{
    public function __invoke(Request $request, string $slug, TransportAvailabilityService $service)
    {
        $transport = Transport::publiclyVisible()->where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'guest_count' => ['nullable', 'integer', 'min:1'],
            'with_driver' => ['required', 'boolean'],
        ]);

        $result = $service->evaluate(
            $transport,
            CarbonImmutable::parse($data['check_in_date']),
            CarbonImmutable::parse($data['check_out_date']),
            $data['guest_count'] ?? 1,
            $data['with_driver'],
        );

        return response()->json(['data' => $result]);
    }
}
