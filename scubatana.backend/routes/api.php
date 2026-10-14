<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanionController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\PassageController;
use App\Http\Controllers\StepController;
use App\Http\Controllers\TerminalController;
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
	Route::post('/me/passages', [PassageController::class, 'store'])->middleware('throttle:30,1');
	Route::get('/bornes', [TerminalController::class, 'index']);
	Route::get('/companion/catalog', [CompanionController::class, 'catalog']);
});
