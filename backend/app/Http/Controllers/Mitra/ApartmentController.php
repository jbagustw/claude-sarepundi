<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Apartment\StoreApartmentRequest;
use App\Http\Requests\Apartment\UpdateApartmentRequest;
use App\Http\Resources\ApartmentResource;
use App\Models\Apartment;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApartmentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Apartment::class);

        $apartments = $request->user()
            ->mitraProfile
            ->apartments()
            ->with(['images', 'facilities'])
            ->latest()
            ->get();

        return ApartmentResource::collection($apartments);
    }

    public function store(StoreApartmentRequest $request)
    {
        $data = $request->validated();
        if (array_key_exists('description', $data)) {
            $data['description'] = HtmlSanitizer::clean($data['description']);
        }

        $apartment = $request->user()->mitraProfile->apartments()->create([
            ...$data,
            'slug' => $this->uniqueSlug($data['name']),
            'status' => 'draft',
        ]);

        $apartment->facilities()->sync($data['facility_ids'] ?? []);

        return (new ApartmentResource($apartment->load(['images', 'facilities'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Apartment $apartment)
    {
        $this->authorize('view', $apartment);

        return new ApartmentResource($apartment->load(['images', 'facilities']));
    }

    public function update(UpdateApartmentRequest $request, Apartment $apartment)
    {
        $data = $request->validated();
        if (array_key_exists('description', $data)) {
            $data['description'] = HtmlSanitizer::clean($data['description']);
        }

        $apartment->update($data);

        if (array_key_exists('facility_ids', $data)) {
            $apartment->facilities()->sync($data['facility_ids'] ?? []);
        }

        return new ApartmentResource($apartment->load(['images', 'facilities']));
    }

    public function destroy(Apartment $apartment)
    {
        $this->authorize('delete', $apartment);

        $apartment->delete();

        return response()->json(['message' => 'Apartment dihapus.']);
    }

    public function submit(Apartment $apartment)
    {
        $this->authorize('submit', $apartment);

        $apartment->update([
            'status' => 'pending_review',
            'rejection_reason' => null,
        ]);

        return new ApartmentResource($apartment);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Apartment::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
