<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\GatheringVenue\StoreGatheringVenueRequest;
use App\Http\Requests\GatheringVenue\UpdateGatheringVenueRequest;
use App\Http\Resources\GatheringVenueResource;
use App\Models\GatheringVenue;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GatheringVenueController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', GatheringVenue::class);

        $venues = $request->user()
            ->mitraProfile
            ->gatheringVenues()
            ->with(['images', 'facilities', 'slots'])
            ->latest()
            ->get();

        return GatheringVenueResource::collection($venues);
    }

    public function store(StoreGatheringVenueRequest $request)
    {
        $data = $request->validated();
        if (array_key_exists('description', $data)) {
            $data['description'] = HtmlSanitizer::clean($data['description']);
        }

        $venue = $request->user()->mitraProfile->gatheringVenues()->create([
            ...$data,
            'slug' => $this->uniqueSlug($data['name']),
            'status' => 'draft',
        ]);

        $venue->facilities()->sync($data['facility_ids'] ?? []);

        return (new GatheringVenueResource($venue->load(['images', 'facilities', 'slots'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(GatheringVenue $gatheringVenue)
    {
        $this->authorize('view', $gatheringVenue);

        return new GatheringVenueResource($gatheringVenue->load(['images', 'facilities', 'slots']));
    }

    public function update(UpdateGatheringVenueRequest $request, GatheringVenue $gatheringVenue)
    {
        $data = $request->validated();
        if (array_key_exists('description', $data)) {
            $data['description'] = HtmlSanitizer::clean($data['description']);
        }

        $gatheringVenue->update($data);

        if (array_key_exists('facility_ids', $data)) {
            $gatheringVenue->facilities()->sync($data['facility_ids'] ?? []);
        }

        return new GatheringVenueResource($gatheringVenue->load(['images', 'facilities', 'slots']));
    }

    public function destroy(GatheringVenue $gatheringVenue)
    {
        $this->authorize('delete', $gatheringVenue);

        $gatheringVenue->delete();

        return response()->json(['message' => 'Lokasi gathering dihapus.']);
    }

    public function submit(GatheringVenue $gatheringVenue)
    {
        $this->authorize('submit', $gatheringVenue);

        $gatheringVenue->update([
            'status' => 'pending_review',
            'rejection_reason' => null,
        ]);

        return new GatheringVenueResource($gatheringVenue);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (GatheringVenue::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
