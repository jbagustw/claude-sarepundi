<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\VillaResource;
use App\Models\Villa;
use Illuminate\Http\Request;

class VillaController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'guests' => ['nullable', 'integer', 'min:1'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'facility_ids' => ['nullable', 'array'],
            'facility_ids.*' => ['integer'],
        ]);

        $villas = Villa::publiclyVisible()
            ->with(['images', 'facilities'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->query('q').'%'))
            ->when($request->filled('city'), fn ($q) => $q->where('city', 'like', '%'.$request->query('city').'%'))
            ->when($request->filled('guests'), fn ($q) => $q->where('capacity_guest', '>=', $request->integer('guests')))
            ->when($request->filled('min_price'), fn ($q) => $q->where('base_price', '>=', $request->integer('min_price')))
            ->when($request->filled('max_price'), fn ($q) => $q->where('base_price', '<=', $request->integer('max_price')))
            ->when($request->filled('facility_ids'), fn ($q) => $q->whereHas(
                'facilities',
                fn ($fq) => $fq->whereIn('facilities.id', $request->query('facility_ids'))
            ))
            ->latest()
            ->paginate(12);

        return VillaResource::collection($villas);
    }

    public function show(string $slug)
    {
        $villa = Villa::publiclyVisible()
            ->with(['images', 'facilities'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('slug', $slug)
            ->firstOrFail();

        return new VillaResource($villa);
    }
}
