<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Glamping\StoreGlampingRequest;
use App\Http\Requests\Glamping\UpdateGlampingRequest;
use App\Http\Resources\GlampingResource;
use App\Models\Glamping;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GlampingController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Glamping::class);

        $glampings = $request->user()
            ->mitraProfile
            ->glampings()
            ->with(['images', 'facilities'])
            ->latest()
            ->get();

        return GlampingResource::collection($glampings);
    }

    public function store(StoreGlampingRequest $request)
    {
        $data = $request->validated();
        if (array_key_exists('description', $data)) {
            $data['description'] = HtmlSanitizer::clean($data['description']);
        }

        $glamping = $request->user()->mitraProfile->glampings()->create([
            ...$data,
            'slug' => $this->uniqueSlug($data['name']),
            'status' => 'draft',
        ]);

        $glamping->facilities()->sync($data['facility_ids'] ?? []);

        return (new GlampingResource($glamping->load(['images', 'facilities'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Glamping $glamping)
    {
        $this->authorize('view', $glamping);

        return new GlampingResource($glamping->load(['images', 'facilities']));
    }

    public function update(UpdateGlampingRequest $request, Glamping $glamping)
    {
        $data = $request->validated();
        if (array_key_exists('description', $data)) {
            $data['description'] = HtmlSanitizer::clean($data['description']);
        }

        $glamping->update($data);

        if (array_key_exists('facility_ids', $data)) {
            $glamping->facilities()->sync($data['facility_ids'] ?? []);
        }

        return new GlampingResource($glamping->load(['images', 'facilities']));
    }

    public function destroy(Glamping $glamping)
    {
        $this->authorize('delete', $glamping);

        $glamping->delete();

        return response()->json(['message' => 'Glamping dihapus.']);
    }

    public function submit(Glamping $glamping)
    {
        $this->authorize('submit', $glamping);

        $glamping->update([
            'status' => 'pending_review',
            'rejection_reason' => null,
        ]);

        return new GlampingResource($glamping);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Glamping::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
