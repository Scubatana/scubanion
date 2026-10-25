<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompanionResource;
use App\Services\PassageService;
use App\Services\PositionService;
use App\Services\TerminalDisplay;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PassageController extends Controller
{
	public function store(Request $request, PassageService $passages, PositionService $positions): JsonResponse
	{
		$data = $request->validate([
			'token' => ['required', 'string', 'max:64'],
			'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
			'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
			'accuracy_m' => ['nullable', 'integer', 'min:0'],
			'mocked' => ['sometimes', 'boolean'],
			'recorded_at' => ['nullable', 'date', 'after_or_equal:'.now()->subHours(config('bornes.max_replay_hours'))->toIso8601String(), 'before_or_equal:'.now()->addMinutes(config('bornes.future_tolerance_minutes'))->toIso8601String()],
		]);

		$position = $this->position($request, $data);

		if ($position !== null) {
			$positions->record($request->user(), $position['latitude'], $position['longitude'], $position['accuracy'], $position['mocked'], $position['recorded_at']);
		}

		$passage = $passages->record($request->user(), $data['token'], $position);
		$display = $passages->display($passage);
		$terminal = $passage->terminal;

		dispatch(fn () => app(TerminalDisplay::class)->show($terminal, $display))->afterResponse();

		$companion = $request->user()->companion()->first();

		return response()->json([
			...$display,
			'terminal' => ['code' => $terminal->code, 'name' => $terminal->name],
			'companion' => $companion === null ? null : new CompanionResource($companion),
		]);
	}

	private function position(Request $request, array $data): ?array
	{
		if (! isset($data['latitude'])) {
			return null;
		}

		return [
			'latitude' => (float) $data['latitude'],
			'longitude' => (float) $data['longitude'],
			'accuracy' => isset($data['accuracy_m']) ? (int) $data['accuracy_m'] : null,
			'mocked' => $request->boolean('mocked'),
			'recorded_at' => isset($data['recorded_at']) ? CarbonImmutable::parse($data['recorded_at'])->setTimezone(config('app.timezone')) : CarbonImmutable::now(),
		];
	}
}
