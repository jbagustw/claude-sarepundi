<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transport\StoreTransportRequest;
use App\Http\Requests\Transport\UpdateTransportRequest;
use App\Http\Resources\TransportResource;
use App\Models\Transport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TransportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Transport::class);

        $transports = $request->user()
            ->mitraProfile
            ->transports()
            ->with('images')
            ->latest()
            ->get();

        return TransportResource::collection($transports);
    }

    public function store(StoreTransportRequest $request)
    {
        $data = $request->validated();

        $transport = $request->user()->mitraProfile->transports()->create([
            ...$data,
            'slug' => $this->uniqueSlug($data['name']),
            'status' => 'draft',
        ]);

        return (new TransportResource($transport->load('images')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Transport $transport)
    {
        $this->authorize('view', $transport);

        return new TransportResource($transport->load('images'));
    }

    public function update(UpdateTransportRequest $request, Transport $transport)
    {
        $transport->update($request->validated());

        return new TransportResource($transport->load('images'));
    }

    public function destroy(Transport $transport)
    {
        $this->authorize('delete', $transport);

        $transport->delete();

        return response()->json(['message' => 'Transport dihapus.']);
    }

    public function submit(Transport $transport)
    {
        $this->authorize('submit', $transport);

        $transport->update([
            'status' => 'pending_review',
            'rejection_reason' => null,
        ]);

        return new TransportResource($transport);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Transport::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
