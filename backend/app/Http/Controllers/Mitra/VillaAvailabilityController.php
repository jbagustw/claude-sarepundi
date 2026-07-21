<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Villa\UpdateAvailabilityRequest;
use App\Http\Resources\VillaAvailabilityResource;
use App\Models\Villa;
use Illuminate\Http\Request;

class VillaAvailabilityController extends Controller
{
    public function index(Request $request, Villa $villa)
    {
        $this->authorize('view', $villa);

        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $overrides = $villa->availability()
            ->whereDate('date', '>=', $request->query('from'))
            ->whereDate('date', '<=', $request->query('to'))
            ->orderBy('date')
            ->get();

        return VillaAvailabilityResource::collection($overrides);
    }

    public function update(UpdateAvailabilityRequest $request, Villa $villa)
    {
        $data = $request->validated();

        foreach ($data['dates'] as $date) {
            $villa->availability()->updateOrCreate(
                ['date' => $date],
                [
                    'is_available' => $data['is_available'],
                    'custom_price' => $data['custom_price'] ?? null,
                    'min_stay' => $data['min_stay'] ?? null,
                ]
            );
        }

        $overrides = $villa->availability()
            ->whereIn('date', $data['dates'])
            ->orderBy('date')
            ->get();

        return VillaAvailabilityResource::collection($overrides);
    }
}
