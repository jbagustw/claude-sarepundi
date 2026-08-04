<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Apartment\RejectApartmentRequest;
use App\Http\Resources\ApartmentResource;
use App\Models\Apartment;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApartmentModerationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending_review');

        $request->validate([
            'status' => ['sometimes', Rule::in(['draft', 'pending_review', 'published', 'rejected', 'inactive'])],
        ]);

        $apartments = Apartment::with(['images', 'facilities', 'mitraProfile'])
            ->where('status', $status)
            ->latest()
            ->get();

        return ApartmentResource::collection($apartments);
    }

    public function approve(Request $request, Apartment $apartment, NotificationService $notifications)
    {
        abort_unless($apartment->status === 'pending_review', 422, 'Apartment ini tidak sedang menunggu review.');

        $apartment->update([
            'status' => 'published',
            'rejection_reason' => null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $notifications->notify(
            $apartment->mitraProfile->user,
            'apartment_approved',
            'Apartment disetujui',
            "Apartment \"{$apartment->name}\" sudah disetujui dan tampil di pencarian publik."
        );

        return new ApartmentResource($apartment->load(['images', 'facilities']));
    }

    public function reject(RejectApartmentRequest $request, Apartment $apartment, NotificationService $notifications)
    {
        abort_unless($apartment->status === 'pending_review', 422, 'Apartment ini tidak sedang menunggu review.');

        $apartment->update([
            'status' => 'rejected',
            'rejection_reason' => $request->validated('reason'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $notifications->notify(
            $apartment->mitraProfile->user,
            'apartment_rejected',
            'Apartment ditolak',
            "Apartment \"{$apartment->name}\" ditolak. Alasan: {$apartment->rejection_reason}"
        );

        return new ApartmentResource($apartment->load(['images', 'facilities']));
    }
}
