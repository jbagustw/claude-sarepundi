<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Villa\RejectVillaRequest;
use App\Http\Resources\VillaResource;
use App\Models\Villa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VillaModerationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending_review');

        $request->validate([
            'status' => ['sometimes', Rule::in(['draft', 'pending_review', 'published', 'rejected', 'inactive'])],
        ]);

        $villas = Villa::with(['images', 'facilities', 'mitraProfile'])
            ->where('status', $status)
            ->latest()
            ->get();

        return VillaResource::collection($villas);
    }

    public function approve(Request $request, Villa $villa)
    {
        abort_unless($villa->status === 'pending_review', 422, 'Villa ini tidak sedang menunggu review.');

        $villa->update([
            'status' => 'published',
            'rejection_reason' => null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return new VillaResource($villa->load(['images', 'facilities']));
    }

    public function reject(RejectVillaRequest $request, Villa $villa)
    {
        abort_unless($villa->status === 'pending_review', 422, 'Villa ini tidak sedang menunggu review.');

        $villa->update([
            'status' => 'rejected',
            'rejection_reason' => $request->validated('reason'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return new VillaResource($villa->load(['images', 'facilities']));
    }
}
