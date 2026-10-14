<?php

namespace App\Http\Controllers;

use App\Services\PositionService;
use App\Services\QuestEngine;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PositionController extends Controller
{
	private const CLOCK_TOLERANCE_MINUTES = 5;

	private const OFFLINE_MAX_HOURS = 24;

	public function store(Request $request, PositionService $positions, QuestEngine $quests): Response
	{
		$data = $request->validate([
			'latitude' => ['required', 'numeric', 'between:-90,90'],
			'longitude' => ['required', 'numeric', 'between:-180,180'],
			'accuracy_m' => ['nullable', 'integer', 'min:0'],
			'mocked' => ['sometimes', 'boolean'],
			'recorded_at' => ['nullable', 'date', 'before_or_equal:'.now()->addMinutes(self::CLOCK_TOLERANCE_MINUTES)->toIso8601String()],
		]);

		$latitude = (float) $data['latitude'];
		$longitude = (float) $data['longitude'];
		$accuracy = isset($data['accuracy_m']) ? (int) $data['accuracy_m'] : null;
		$recordedAt = isset($data['recorded_at']) ? CarbonImmutable::parse($data['recorded_at'])->setTimezone(config('app.timezone')) : CarbonImmutable::now();

		$positions->record($request->user(), $latitude, $longitude, $accuracy, $request->boolean('mocked'), $recordedAt);

		if ($recordedAt->gte(now()->subHours(self::OFFLINE_MAX_HOURS))) {
			$quests->checkInAround($request->user(), $latitude, $longitude, $accuracy, $recordedAt);
		}

		return response()->noContent();
	}
}
