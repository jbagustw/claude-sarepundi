<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MitraProfile;
use App\Models\User;
use App\Models\Villa;

class DashboardController extends Controller
{
    public function stats()
    {
        return response()->json(['data' => [
            'users' => [
                'total' => User::role('user')->count(),
                'total_mitra' => User::role('mitra')->count(),
                'suspended' => User::where('status', 'suspended')->count(),
            ],
            'mitras' => [
                'pending_approval' => MitraProfile::where('status', 'pending')->count(),
                'approved' => MitraProfile::where('status', 'approved')->count(),
            ],
            'villas' => [
                'pending_review' => Villa::where('status', 'pending_review')->count(),
                'published' => Villa::where('status', 'published')->count(),
                'total' => Villa::count(),
            ],
            'bookings' => [
                'total' => Booking::count(),
                'awaiting_payment' => Booking::where('status', 'pending_payment')->count(),
                'confirmed' => Booking::where('status', 'dikonfirmasi')->count(),
                'completed' => Booking::where('status', 'selesai')->count(),
                'cancelled' => Booking::whereIn('status', ['dibatalkan_mitra', 'dibatalkan_user'])->count(),
            ],
            // Commission is only counted as "earned" once a stay is
            // actually completed (aligned with the payout trigger design
            // for milestone 9) — bookings still in progress or refunded
            // don't represent settled platform revenue yet.
            'commission_earned' => (int) Booking::where('status', 'selesai')->sum('commission_amount'),
        ]]);
    }
}
