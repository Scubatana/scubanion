<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanionController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\StepController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/code', [AuthController::class, 'requestCode'])->middleware('throttle:login-code');
Route::post('/auth/verify', [AuthController::class, 'verify'])->middleware('throttle:login-verify');

Route::middleware('auth:sanctum')->group(function () {
	Route::post('/auth/logout', [AuthController::class, 'logout']);
	Route::get('/me', [MeController::class, 'show']);
	Route::put('/me/profile', [MeController::class, 'updateProfile']);
	Route::put('/me/companion', [CompanionController::class, 'update']);
	Route::get('/me/steps', [StepController::class, 'index']);
	Route::post('/me/steps', [StepController::class, 'store']);
	Route::put('/me/goal', [StepController::class, 'updateGoal']);
	Route::get('/companion/catalog', [CompanionController::class, 'catalog']);
	Route::get('/me/friends', [FriendController::class, 'index']);
	Route::post('/me/friends', [FriendController::class, 'store'])->middleware('throttle:friend-invite');
	Route::post('/me/friends/{friendship}/accept', [FriendController::class, 'accept']);
	Route::delete('/me/friends/{friendship}', [FriendController::class, 'destroy']);
});
