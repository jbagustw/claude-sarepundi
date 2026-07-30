<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Homestay\StoreHomestayRequest;
use App\Http\Requests\Homestay\UpdateHomestayRequest;
use App\Http\Resources\HomestayResource;
use App\Models\Homestay;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HomestayController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Homestay::class);

        $homestays = $request->user()
            ->mitraProfile
            ->homestays()
            ->with(['images', 'facilities'])
            ->latest()
            ->get();

        return HomestayResource::collection($homestays);
    }

    public function store(StoreHomestayRequest $request)
    {
        $data = $request->validated();

        $homestay = $request->user()->mitraProfile->homestays()->create([
            ...$data,
            'slug' => $this->uniqueSlug($data['name']),
            'status' => 'draft',
        ]);

        $homestay->facilities()->sync($data['facility_ids'] ?? []);

        return (new HomestayResource($homestay->load(['images', 'facilities'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Homestay $homestay)
    {
        $this->authorize('view', $homestay);

        return new HomestayResource($homestay->load(['images', 'facilities']));
    }

    public function update(UpdateHomestayRequest $request, Homestay $homestay)
    {
        $data = $request->validated();

        $homestay->update($data);

        if (array_key_exists('facility_ids', $data)) {
            $homestay->facilities()->sync($data['facility_ids'] ?? []);
        }

        return new HomestayResource($homestay->load(['images', 'facilities']));
    }

    public function destroy(Homestay $homestay)
    {
        $this->authorize('delete', $homestay);

        $homestay->delete();

        return response()->json(['message' => 'Homestay dihapus.']);
    }

    public function submit(Homestay $homestay)
    {
        $this->authorize('submit', $homestay);

        $homestay->update([
            'status' => 'pending_review',
            'rejection_reason' => null,
        ]);

        return new HomestayResource($homestay);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Homestay::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
