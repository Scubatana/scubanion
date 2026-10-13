<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowStepsRequest;
use App\Http\Requests\StoreStepsRequest;
use App\Http\Requests\UpdateGoalRequest;
use App\Http\Resources\CompanionResource;
use App\Services\CompanionProgressService;
use App\Services\StepService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class StepController extends Controller
{
	public function index(ShowStepsRequest $request, StepService $steps): JsonResponse
	{
		$month = $request->validated('month');

		return response()->json($steps->month(
			$request->user(),
			$month ? CarbonImmutable::createFromFormat('!Y-m', $month) : CarbonImmutable::now(),
		));
	}

	public function updateGoal(UpdateGoalRequest $request, StepService $steps, CompanionProgressService $progress): JsonResponse
	{
		$user = $request->user();
		$steps->changeGoal($user, $request->validated('daily_step_goal'));
		$companion = $progress->refresh($user);

		return response()->json([
			'today' => $steps->today($user),
			'streak' => $steps->streak($user),
			'companion' => $companion ? new CompanionResource($companion) : null,
		]);
	}

	public function store(StoreStepsRequest $request, StepService $steps, CompanionProgressService $progress): JsonResponse
	{
		$user = $request->user();

		foreach ($request->validated('days') as $entry) {
			$steps->record($user, $request->validated('device_id'), $entry['day'], $entry['steps']);
		}

		$companion = $progress->refresh($user);

		return response()->json([
			'today' => $steps->today($user),
			'streak' => $steps->streak($user),
			'total_steps' => (int) $user->activities()->sum('steps'),
			'companion' => $companion ? new CompanionResource($companion) : null,
		]);
	}
}
