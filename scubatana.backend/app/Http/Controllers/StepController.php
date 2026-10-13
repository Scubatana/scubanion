<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowStepsRequest;
use App\Http\Requests\StoreStepsRequest;
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

	public function store(StoreStepsRequest $request, StepService $steps): JsonResponse
	{
		$user = $request->user();

		foreach ($request->validated('days') as $entry) {
			$steps->record($user, $request->validated('device_id'), $entry['day'], $entry['steps']);
		}

		return response()->json([
			'today' => $steps->today($user),
			'streak' => $steps->streak($user),
			'total_steps' => (int) $user->activities()->sum('steps'),
		]);
	}
}
