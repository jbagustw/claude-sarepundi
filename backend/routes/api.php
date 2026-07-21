<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/user/ping', fn () => response()->json(['message' => 'pong from user area']))
        ->middleware('role:user');

    Route::get('/mitra/ping', fn () => response()->json(['message' => 'pong from mitra area']))
        ->middleware('role:mitra');

    Route::get('/admin/ping', fn () => response()->json(['message' => 'pong from admin area']))
        ->middleware('role:admin');
});
