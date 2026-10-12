<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\CompanionResource;
use App\Http\Resources\PlayerResource;
use App\Services\CompanionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
	public function show(Request $request): JsonResponse
	{
		$user = $request->user();
		$today = now()->toDateString();
		$companion = $user->companion;

		return response()->json([
			'player' => new PlayerResource($user),
			'companion' => $companion ? new CompanionResource($companion) : null,
			'today' => [
				'date' => $today,
				'steps' => (int) $user->activities()->whereDate('day', $today)->value('steps'),
				'goal' => $user->daily_step_goal,
			],
		]);
	}

	public function updateProfile(UpdateProfileRequest $request, CompanionService $companions): JsonResponse
	{
		$user = $request->user();
		$user->update([
			'pseudo' => $request->validated('pseudo'),
			'birth_date' => $request->validated('birth_date'),
			'gender' => $request->validated('gender'),
			'ambition' => $request->validated('ambition'),
		]);

		return response()->json([
			'player' => new PlayerResource($user),
			'companion' => new CompanionResource($companions->createFor($user)),
		]);
	}
}
