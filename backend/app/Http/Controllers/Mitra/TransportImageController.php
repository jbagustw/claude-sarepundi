<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transport\StoreTransportImageRequest;
use App\Http\Resources\TransportImageResource;
use App\Models\Transport;
use App\Models\TransportImage;
use Illuminate\Support\Facades\Storage;

class TransportImageController extends Controller
{
    public function store(StoreTransportImageRequest $request, Transport $transport)
    {
        $path = $request->file('image')->store('transport-images', 'public');

        $image = $transport->images()->create([
            'image_url' => $path,
            'is_primary' => $request->boolean('is_primary') || $transport->images()->doesntExist(),
            'sort_order' => $transport->images()->count(),
        ]);

        if ($image->is_primary) {
            $transport->images()->whereKeyNot($image->id)->update(['is_primary' => false]);
        }

        return (new TransportImageResource($image))->response()->setStatusCode(201);
    }

    public function destroy(Transport $transport, TransportImage $image)
    {
        $this->authorize('update', $transport);

        if ($image->transport_id !== $transport->id) {
            abort(404);
        }

        Storage::disk('public')->delete($image->image_url);
        $image->delete();

        return response()->json(['message' => 'Foto dihapus.']);
    }
}
