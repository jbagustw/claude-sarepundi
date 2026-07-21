<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MitraProfileResource;
use App\Models\MitraProfile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MitraModerationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected'])],
        ]);

        $mitras = MitraProfile::with('user')
            ->where('status', $status)
            ->latest()
            ->get();

        return MitraProfileResource::collection($mitras);
    }

    public function approve(Request $request, MitraProfile $mitra)
    {
        abort_unless($mitra->status === 'pending', 422, 'Mitra ini tidak sedang menunggu approval.');

        $mitra->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return new MitraProfileResource($mitra->load('user'));
    }

    public function reject(Request $request, MitraProfile $mitra)
    {
        abort_unless($mitra->status === 'pending', 422, 'Mitra ini tidak sedang menunggu approval.');

        $mitra->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return new MitraProfileResource($mitra->load('user'));
    }

    /**
     * Override this mitra's commission rate. PRD: default 10%, admin may
     * override per mitra when needed. Passing null resets to the
     * platform default.
     */
    public function updateCommission(Request $request, MitraProfile $mitra)
    {
        $data = $request->validate([
            'commission_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $mitra->update(['commission_rate' => $data['commission_rate']]);

        return new MitraProfileResource($mitra->load('user'));
    }
}
