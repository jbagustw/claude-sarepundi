<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Homestay\RejectHomestayRequest;
use App\Http\Resources\HomestayResource;
use App\Models\Homestay;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HomestayModerationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending_review');

        $request->validate([
            'status' => ['sometimes', Rule::in(['draft', 'pending_review', 'published', 'rejected', 'inactive'])],
        ]);

        $homestays = Homestay::with(['images', 'facilities', 'mitraProfile'])
            ->where('status', $status)
            ->latest()
            ->get();

        return HomestayResource::collection($homestays);
    }

    public function approve(Request $request, Homestay $homestay, NotificationService $notifications)
    {
        abort_unless($homestay->status === 'pending_review', 422, 'Homestay ini tidak sedang menunggu review.');

        $homestay->update([
            'status' => 'published',
            'rejection_reason' => null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $notifications->notify(
            $homestay->mitraProfile->user,
            'homestay_approved',
            'Homestay disetujui',
            "Homestay \"{$homestay->name}\" sudah disetujui dan tampil di pencarian publik."
        );

        return new HomestayResource($homestay->load(['images', 'facilities']));
    }

    public function reject(RejectHomestayRequest $request, Homestay $homestay, NotificationService $notifications)
    {
        abort_unless($homestay->status === 'pending_review', 422, 'Homestay ini tidak sedang menunggu review.');

        $homestay->update([
            'status' => 'rejected',
            'rejection_reason' => $request->validated('reason'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $notifications->notify(
            $homestay->mitraProfile->user,
            'homestay_rejected',
            'Homestay ditolak',
            "Homestay \"{$homestay->name}\" ditolak. Alasan: {$homestay->rejection_reason}"
        );

        return new HomestayResource($homestay->load(['images', 'facilities']));
    }
}
