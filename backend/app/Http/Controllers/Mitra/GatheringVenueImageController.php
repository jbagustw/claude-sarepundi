<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\GatheringVenue\StoreGatheringVenueImageRequest;
use App\Http\Resources\GatheringVenueImageResource;
use App\Models\GatheringVenue;
use App\Models\GatheringVenueImage;
use Illuminate\Support\Facades\Storage;

class GatheringVenueImageController extends Controller
{
    public function store(StoreGatheringVenueImageRequest $request, GatheringVenue $gatheringVenue)
    {
        $path = $request->file('image')->store('gathering-venue-images', 'public');

        $image = $gatheringVenue->images()->create([
            'image_url' => $path,
            'is_primary' => $request->boolean('is_primary') || $gatheringVenue->images()->doesntExist(),
            'sort_order' => $gatheringVenue->images()->count(),
        ]);

        if ($image->is_primary) {
            $gatheringVenue->images()->whereKeyNot($image->id)->update(['is_primary' => false]);
        }

        return (new GatheringVenueImageResource($image))->response()->setStatusCode(201);
    }

    public function destroy(GatheringVenue $gatheringVenue, GatheringVenueImage $image)
    {
        $this->authorize('update', $gatheringVenue);

        if ($image->gathering_venue_id !== $gatheringVenue->id) {
            abort(404);
        }

        Storage::disk('public')->delete($image->image_url);
        $image->delete();

        return response()->json(['message' => 'Foto dihapus.']);
    }
}
