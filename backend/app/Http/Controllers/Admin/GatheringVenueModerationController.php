<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GatheringVenue\RejectGatheringVenueRequest;
use App\Http\Resources\GatheringVenueResource;
use App\Models\GatheringVenue;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GatheringVenueModerationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending_review');

        $request->validate([
            'status' => ['sometimes', Rule::in(['draft', 'pending_review', 'published', 'rejected', 'inactive'])],
        ]);

        $venues = GatheringVenue::with(['images', 'facilities', 'slots', 'mitraProfile'])
            ->where('status', $status)
            ->latest()
            ->get();

        return GatheringVenueResource::collection($venues);
    }

    public function approve(Request $request, GatheringVenue $gatheringVenue, NotificationService $notifications)
    {
        abort_unless($gatheringVenue->status === 'pending_review', 422, 'Lokasi gathering ini tidak sedang menunggu review.');

        $gatheringVenue->update([
            'status' => 'published',
            'rejection_reason' => null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $notifications->notify(
            $gatheringVenue->mitraProfile->user,
            'gathering_venue_approved',
            'Lokasi gathering disetujui',
            "Lokasi gathering \"{$gatheringVenue->name}\" sudah disetujui dan tampil di pencarian publik."
        );

        return new GatheringVenueResource($gatheringVenue->load(['images', 'facilities', 'slots']));
    }

    public function reject(RejectGatheringVenueRequest $request, GatheringVenue $gatheringVenue, NotificationService $notifications)
    {
        abort_unless($gatheringVenue->status === 'pending_review', 422, 'Lokasi gathering ini tidak sedang menunggu review.');

        $gatheringVenue->update([
            'status' => 'rejected',
            'rejection_reason' => $request->validated('reason'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $notifications->notify(
            $gatheringVenue->mitraProfile->user,
            'gathering_venue_rejected',
            'Lokasi gathering ditolak',
            "Lokasi gathering \"{$gatheringVenue->name}\" ditolak. Alasan: {$gatheringVenue->rejection_reason}"
        );

        return new GatheringVenueResource($gatheringVenue->load(['images', 'facilities', 'slots']));
    }
}
