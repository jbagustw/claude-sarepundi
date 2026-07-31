<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransportResource;
use App\Models\Transport;
use Illuminate\Http\Request;

class TransportController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'with_driver' => ['nullable', 'boolean'],
        ]);

        $transports = Transport::publiclyVisible()
            ->with('images')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->query('q').'%'))
            ->when($request->filled('city'), fn ($q) => $q->where('city', 'like', '%'.$request->query('city').'%'))
            ->when($request->filled('capacity'), fn ($q) => $q->where('capacity', '>=', $request->integer('capacity')))
            ->when($request->boolean('with_driver'), fn ($q) => $q->whereNotNull('price_per_day_with_driver'))
            ->latest()
            ->paginate(12);

        return TransportResource::collection($transports);
    }

    public function show(string $slug)
    {
        $transport = Transport::publiclyVisible()
            ->with('images')
            ->where('slug', $slug)
            ->firstOrFail();

        return new TransportResource($transport);
    }
}
