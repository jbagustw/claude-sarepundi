<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Glamping\RejectGlampingRequest;
use App\Http\Resources\GlampingResource;
use App\Models\Glamping;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GlampingModerationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending_review');

        $request->validate([
            'status' => ['sometimes', Rule::in(['draft', 'pending_review', 'published', 'rejected', 'inactive'])],
        ]);

        $glampings = Glamping::with(['images', 'facilities', 'mitraProfile'])
            ->where('status', $status)
            ->latest()
            ->get();

        return GlampingResource::collection($glampings);
    }

    public function approve(Request $request, Glamping $glamping, NotificationService $notifications)
    {
        abort_unless($glamping->status === 'pending_review', 422, 'Glamping ini tidak sedang menunggu review.');

        $glamping->update([
            'status' => 'published',
            'rejection_reason' => null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $notifications->notify(
            $glamping->mitraProfile->user,
            'glamping_approved',
            'Glamping disetujui',
            "Glamping \"{$glamping->name}\" sudah disetujui dan tampil di pencarian publik."
        );

        return new GlampingResource($glamping->load(['images', 'facilities']));
    }

    public function reject(RejectGlampingRequest $request, Glamping $glamping, NotificationService $notifications)
    {
        abort_unless($glamping->status === 'pending_review', 422, 'Glamping ini tidak sedang menunggu review.');

        $glamping->update([
            'status' => 'rejected',
            'rejection_reason' => $request->validated('reason'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $notifications->notify(
            $glamping->mitraProfile->user,
            'glamping_rejected',
            'Glamping ditolak',
            "Glamping \"{$glamping->name}\" ditolak. Alasan: {$glamping->rejection_reason}"
        );

        return new GlampingResource($glamping->load(['images', 'facilities']));
    }
}
