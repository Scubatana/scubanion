<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/code', [AuthController::class, 'requestCode'])->middleware('throttle:login-code');
Route::post('/auth/verify', [AuthController::class, 'verify'])->middleware('throttle:login-verify');

Route::middleware('auth:sanctum')->group(function () {
	Route::post('/auth/logout', [AuthController::class, 'logout']);
});
