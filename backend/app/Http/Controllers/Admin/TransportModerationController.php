<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transport\RejectTransportRequest;
use App\Http\Resources\TransportResource;
use App\Models\Transport;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransportModerationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending_review');

        $request->validate([
            'status' => ['sometimes', Rule::in(['draft', 'pending_review', 'published', 'rejected', 'inactive'])],
        ]);

        $transports = Transport::with(['images', 'mitraProfile'])
            ->where('status', $status)
            ->latest()
            ->get();

        return TransportResource::collection($transports);
    }

    public function approve(Request $request, Transport $transport, NotificationService $notifications)
    {
        abort_unless($transport->status === 'pending_review', 422, 'Transport ini tidak sedang menunggu review.');

        $transport->update([
            'status' => 'published',
            'rejection_reason' => null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $notifications->notify(
            $transport->mitraProfile->user,
            'transport_approved',
            'Transport disetujui',
            "Transport \"{$transport->name}\" sudah disetujui dan tampil di pencarian publik."
        );

        return new TransportResource($transport->load('images'));
    }

    public function reject(RejectTransportRequest $request, Transport $transport, NotificationService $notifications)
    {
        abort_unless($transport->status === 'pending_review', 422, 'Transport ini tidak sedang menunggu review.');

        $transport->update([
            'status' => 'rejected',
            'rejection_reason' => $request->validated('reason'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $notifications->notify(
            $transport->mitraProfile->user,
            'transport_rejected',
            'Transport ditolak',
            "Transport \"{$transport->name}\" ditolak. Alasan: {$transport->rejection_reason}"
        );

        return new TransportResource($transport->load('images'));
    }
}
