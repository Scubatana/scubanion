<?php

namespace App\Http\Controllers;

use App\Services\PositionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PositionController extends Controller
{
	private const CLOCK_TOLERANCE_MINUTES = 5;

	public function store(Request $request, PositionService $positions): Response
	{
		$data = $request->validate([
			'latitude' => ['required', 'numeric', 'between:-90,90'],
			'longitude' => ['required', 'numeric', 'between:-180,180'],
			'accuracy_m' => ['nullable', 'integer', 'min:0'],
			'mocked' => ['sometimes', 'boolean'],
			'recorded_at' => ['nullable', 'date', 'before_or_equal:'.now()->addMinutes(self::CLOCK_TOLERANCE_MINUTES)->toIso8601String()],
		]);

		$positions->record(
			$request->user(),
			(float) $data['latitude'],
			(float) $data['longitude'],
			isset($data['accuracy_m']) ? (int) $data['accuracy_m'] : null,
			$request->boolean('mocked'),
			isset($data['recorded_at']) ? CarbonImmutable::parse($data['recorded_at'])->setTimezone(config('app.timezone')) : CarbonImmutable::now(),
		);

		return response()->noContent();
	}
}
