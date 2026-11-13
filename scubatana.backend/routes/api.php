<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlockController;
use App\Http\Controllers\CompanionController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\PassageController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StepController;
use App\Http\Controllers\TerminalController;
use App\Http\Controllers\UserQuestController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/code', [AuthController::class, 'requestCode'])->middleware('throttle:login-code');
Route::post('/auth/verify', [AuthController::class, 'verify'])->middleware('throttle:login-verify');

Route::middleware('auth:sanctum')->group(function () {
	Route::post('/auth/logout', [AuthController::class, 'logout']);
	Route::get('/me', [MeController::class, 'show']);
	Route::delete('/me', [AccountController::class, 'destroy']);
	Route::put('/me/profile', [MeController::class, 'updateProfile']);
	Route::put('/me/privacy', [MeController::class, 'updatePrivacy'])->middleware('throttle:30,1');
	Route::put('/me/companion', [CompanionController::class, 'update']);
	Route::post('/me/position', [PositionController::class, 'store'])->middleware('throttle:10,1');
	Route::get('/map', [MapController::class, 'index']);
	Route::get('/catalog/types', [MapController::class, 'types'])->middleware('cache.headers:private;etag');
	Route::get('/me/steps', [StepController::class, 'index']);
	Route::post('/me/steps', [StepController::class, 'store']);
	Route::get('/me/quests', [UserQuestController::class, 'index']);
	Route::post('/me/quests/{userQuest}/tasks/{questTask}', [UserQuestController::class, 'act'])->middleware('throttle:30,1');
	Route::post('/me/quests/{userQuest}/claim', [UserQuestController::class, 'claim']);
	Route::put('/me/goal', [StepController::class, 'updateGoal']);
	Route::post('/me/passages', [PassageController::class, 'store'])->middleware('throttle:30,1');
	Route::get('/bornes', [TerminalController::class, 'index']);
	Route::get('/companion/catalog', [CompanionController::class, 'catalog']);
	Route::get('/me/friends', [FriendController::class, 'index']);
	Route::post('/me/friends', [FriendController::class, 'store'])->middleware('throttle:friend-invite');
	Route::get('/me/friends/qr', [FriendController::class, 'qr'])->middleware('throttle:30,1');
	Route::post('/me/friends/qr', [FriendController::class, 'scan'])->middleware('throttle:20,1');
	Route::post('/me/friends/{friendship}/accept', [FriendController::class, 'accept']);
	Route::delete('/me/friends/{friendship}', [FriendController::class, 'destroy']);
	Route::get('/me/blocks', [BlockController::class, 'index']);
	Route::post('/me/blocks', [BlockController::class, 'store'])->middleware('throttle:30,1');
	Route::delete('/me/blocks/{block}', [BlockController::class, 'destroy']);
	Route::post('/me/reports', [ReportController::class, 'store'])->middleware('throttle:player-report');
});
