<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayoutResource;
use App\Models\Payout;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayoutController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'completed', 'failed'])],
        ]);

        $payouts = Payout::with('mitraProfile')
            ->withCount('bookings')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest()
            ->get();

        return PayoutResource::collection($payouts);
    }

    public function run(PayoutService $payoutService)
    {
        $payouts = $payoutService->run();

        return PayoutResource::collection(
            collect($payouts)->each->load('mitraProfile')->each->loadCount('bookings')
        );
    }

    public function retry(Payout $payout, PayoutService $payoutService)
    {
        abort_unless($payout->status === 'failed', 422, 'Hanya payout yang gagal yang bisa dicoba ulang.');

        $payoutService->retry($payout);

        return new PayoutResource($payout->fresh()->load('mitraProfile')->loadCount('bookings'));
    }
}
