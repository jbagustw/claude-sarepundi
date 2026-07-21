<?php

use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\MitraModerationController;
use App\Http\Controllers\Admin\PayoutController as AdminPayoutController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VillaModerationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\Mitra\BookingController as MitraBookingController;
use App\Http\Controllers\Mitra\DashboardController as MitraDashboardController;
use App\Http\Controllers\Mitra\PayoutController as MitraPayoutController;
use App\Http\Controllers\Mitra\ProfileController as MitraProfileController;
use App\Http\Controllers\Mitra\ReviewController as MitraReviewController;
use App\Http\Controllers\Mitra\VillaAvailabilityController as MitraVillaAvailabilityController;
use App\Http\Controllers\Mitra\VillaController as MitraVillaController;
use App\Http\Controllers\Mitra\VillaImageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Public\ReviewController as PublicReviewController;
use App\Http\Controllers\Public\VillaAvailabilityController as PublicVillaAvailabilityController;
use App\Http\Controllers\Public\VillaController as PublicVillaController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\XenditWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/facilities', [FacilityController::class, 'index']);
Route::get('/villas', [PublicVillaController::class, 'index']);
Route::get('/villas/{slug}', [PublicVillaController::class, 'show']);
Route::get('/villas/{slug}/availability', PublicVillaAvailabilityController::class);
Route::get('/villas/{slug}/reviews', [PublicReviewController::class, 'index']);

Route::post('/webhooks/xendit', [XenditWebhookController::class, 'handle']);

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    Route::middleware('role:user')->group(function () {
        Route::get('/user/ping', fn () => response()->json(['message' => 'pong from user area']));

        Route::get('/bookings', [BookingController::class, 'index']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::post('/bookings/{booking}/pay', [PaymentController::class, 'store']);
        Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
        Route::post('/bookings/{booking}/review', [ReviewController::class, 'store']);
    });

    Route::middleware('role:mitra')->prefix('mitra')->group(function () {
        Route::get('/ping', fn () => response()->json(['message' => 'pong from mitra area']));

        Route::get('/stats', [MitraDashboardController::class, 'stats']);

        Route::get('/profile', [MitraProfileController::class, 'show']);
        Route::patch('/profile', [MitraProfileController::class, 'update']);

        Route::get('/payouts', [MitraPayoutController::class, 'index']);

        Route::apiResource('villas', MitraVillaController::class);
        Route::post('/villas/{villa}/submit', [MitraVillaController::class, 'submit']);
        Route::post('/villas/{villa}/images', [VillaImageController::class, 'store']);
        Route::delete('/villas/{villa}/images/{image}', [VillaImageController::class, 'destroy']);

        Route::get('/villas/{villa}/availability', [MitraVillaAvailabilityController::class, 'index']);
        Route::put('/villas/{villa}/availability', [MitraVillaAvailabilityController::class, 'update']);

        Route::get('/bookings', [MitraBookingController::class, 'index']);
        Route::post('/bookings/{booking}/accept', [MitraBookingController::class, 'accept']);
        Route::post('/bookings/{booking}/reject', [MitraBookingController::class, 'reject']);

        Route::get('/reviews', [MitraReviewController::class, 'index']);
        Route::post('/reviews/{review}/reply', [MitraReviewController::class, 'reply']);
    });

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/ping', fn () => response()->json(['message' => 'pong from admin area']));

        Route::get('/villas', [VillaModerationController::class, 'index']);
        Route::post('/villas/{villa}/approve', [VillaModerationController::class, 'approve']);
        Route::post('/villas/{villa}/reject', [VillaModerationController::class, 'reject']);

        Route::get('/mitras', [MitraModerationController::class, 'index']);
        Route::post('/mitras/{mitra}/approve', [MitraModerationController::class, 'approve']);
        Route::post('/mitras/{mitra}/reject', [MitraModerationController::class, 'reject']);
        Route::patch('/mitras/{mitra}/commission', [MitraModerationController::class, 'updateCommission']);

        Route::get('/stats', [AdminDashboardController::class, 'stats']);
        Route::get('/bookings', [AdminBookingController::class, 'index']);

        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend']);
        Route::post('/users/{user}/activate', [AdminUserController::class, 'activate']);

        Route::get('/payouts', [AdminPayoutController::class, 'index']);
        Route::post('/payouts/run', [AdminPayoutController::class, 'run']);
        Route::post('/payouts/{payout}/retry', [AdminPayoutController::class, 'retry']);
    });
});
