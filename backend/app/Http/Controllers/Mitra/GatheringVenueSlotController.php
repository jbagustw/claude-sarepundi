<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\GatheringVenue\StoreGatheringVenueSlotRequest;
use App\Http\Requests\GatheringVenue\UpdateGatheringVenueSlotRequest;
use App\Http\Resources\GatheringVenueSlotResource;
use App\Models\GatheringVenue;
use App\Models\GatheringVenueSlot;

class GatheringVenueSlotController extends Controller
{
    public function store(StoreGatheringVenueSlotRequest $request, GatheringVenue $gatheringVenue)
    {
        $slot = $gatheringVenue->slots()->create($request->validated());

        return (new GatheringVenueSlotResource($slot))->response()->setStatusCode(201);
    }

    public function update(UpdateGatheringVenueSlotRequest $request, GatheringVenue $gatheringVenue, GatheringVenueSlot $slot)
    {
        if ($slot->gathering_venue_id !== $gatheringVenue->id) {
            abort(404);
        }

        $slot->update($request->validated());

        return new GatheringVenueSlotResource($slot);
    }

    public function destroy(GatheringVenue $gatheringVenue, GatheringVenueSlot $slot)
    {
        $this->authorize('update', $gatheringVenue);

        if ($slot->gathering_venue_id !== $gatheringVenue->id) {
            abort(404);
        }

        abort_if(
            $slot->bookings()->exists(),
            422,
            'Slot ini tidak bisa dihapus karena sudah ada booking untuknya. Nonaktifkan slot ini saja.'
        );

        $slot->delete();

        return response()->json(['message' => 'Slot dihapus.']);
    }
}
