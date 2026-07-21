<?php

use App\Http\Controllers\Admin\MitraModerationController;
use App\Http\Controllers\Admin\VillaModerationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\Mitra\VillaAvailabilityController as MitraVillaAvailabilityController;
use App\Http\Controllers\Mitra\VillaController as MitraVillaController;
use App\Http\Controllers\Mitra\VillaImageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Public\VillaAvailabilityController as PublicVillaAvailabilityController;
use App\Http\Controllers\Public\VillaController as PublicVillaController;
use App\Http\Controllers\XenditWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/facilities', [FacilityController::class, 'index']);
Route::get('/villas', [PublicVillaController::class, 'index']);
Route::get('/villas/{slug}', [PublicVillaController::class, 'show']);
Route::get('/villas/{slug}/availability', PublicVillaAvailabilityController::class);

Route::post('/webhooks/xendit', [XenditWebhookController::class, 'handle']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('role:user')->group(function () {
        Route::get('/user/ping', fn () => response()->json(['message' => 'pong from user area']));

        Route::get('/bookings', [BookingController::class, 'index']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::post('/bookings/{booking}/pay', [PaymentController::class, 'store']);
    });

    Route::middleware('role:mitra')->prefix('mitra')->group(function () {
        Route::get('/ping', fn () => response()->json(['message' => 'pong from mitra area']));

        Route::apiResource('villas', MitraVillaController::class);
        Route::post('/villas/{villa}/submit', [MitraVillaController::class, 'submit']);
        Route::post('/villas/{villa}/images', [VillaImageController::class, 'store']);
        Route::delete('/villas/{villa}/images/{image}', [VillaImageController::class, 'destroy']);

        Route::get('/villas/{villa}/availability', [MitraVillaAvailabilityController::class, 'index']);
        Route::put('/villas/{villa}/availability', [MitraVillaAvailabilityController::class, 'update']);
    });

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/ping', fn () => response()->json(['message' => 'pong from admin area']));

        Route::get('/villas', [VillaModerationController::class, 'index']);
        Route::post('/villas/{villa}/approve', [VillaModerationController::class, 'approve']);
        Route::post('/villas/{villa}/reject', [VillaModerationController::class, 'reject']);

        Route::get('/mitras', [MitraModerationController::class, 'index']);
        Route::post('/mitras/{mitra}/approve', [MitraModerationController::class, 'approve']);
        Route::post('/mitras/{mitra}/reject', [MitraModerationController::class, 'reject']);
    });
});
