<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Villa\StoreVillaRequest;
use App\Http\Requests\Villa\UpdateVillaRequest;
use App\Http\Resources\VillaResource;
use App\Models\Villa;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VillaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Villa::class);

        $villas = $request->user()
            ->mitraProfile
            ->villas()
            ->with(['images', 'facilities'])
            ->latest()
            ->get();

        return VillaResource::collection($villas);
    }

    public function store(StoreVillaRequest $request)
    {
        $data = $request->validated();
        if (array_key_exists('description', $data)) {
            $data['description'] = HtmlSanitizer::clean($data['description']);
        }

        $villa = $request->user()->mitraProfile->villas()->create([
            ...$data,
            'slug' => $this->uniqueSlug($data['name']),
            'status' => 'draft',
        ]);

        $villa->facilities()->sync($data['facility_ids'] ?? []);

        return (new VillaResource($villa->load(['images', 'facilities'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Villa $villa)
    {
        $this->authorize('view', $villa);

        return new VillaResource($villa->load(['images', 'facilities']));
    }

    public function update(UpdateVillaRequest $request, Villa $villa)
    {
        $data = $request->validated();
        if (array_key_exists('description', $data)) {
            $data['description'] = HtmlSanitizer::clean($data['description']);
        }

        $villa->update($data);

        if (array_key_exists('facility_ids', $data)) {
            $villa->facilities()->sync($data['facility_ids'] ?? []);
        }

        return new VillaResource($villa->load(['images', 'facilities']));
    }

    public function destroy(Villa $villa)
    {
        $this->authorize('delete', $villa);

        $villa->delete();

        return response()->json(['message' => 'Villa dihapus.']);
    }

    public function submit(Villa $villa)
    {
        $this->authorize('submit', $villa);

        $villa->update([
            'status' => 'pending_review',
            'rejection_reason' => null,
        ]);

        return new VillaResource($villa);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Villa::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
