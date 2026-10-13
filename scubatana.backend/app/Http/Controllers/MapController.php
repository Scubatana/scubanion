<?php

namespace App\Http\Controllers;

use App\Models\Place;
use App\Models\PlacePoint;
use App\Models\PlaceType;
use App\Models\TaskType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MapController extends Controller
{
	public function index(Request $request): JsonResponse
	{
		[$minLng, $minLat, $maxLng, $maxLat] = $this->boundingBox($request);
		$limit = config('positions.map_max_places');

		$places = Place::with(['type', 'points'])
			->where('active', true)
			->whereHas('type', fn (Builder $type) => $type->where('on_map', true)->where('active', true))
			->where('min_lat', '<=', $maxLat)->where('max_lat', '>=', $minLat)
			->where('min_lng', '<=', $maxLng)->where('max_lng', '>=', $minLng)
			->orderByRaw('ABS(latitude - ?) + ABS(longitude - ?)', [($minLat + $maxLat) / 2, ($minLng + $maxLng) / 2])
			->orderBy('id')
			->limit($limit + 1)
			->get();
		$truncated = $places->count() > $limit;
		$places = $places->take($limit);

		return response()->json([
			'truncated' => $truncated,
			'places' => $places->map(fn (Place $place) => [
				'id' => $place->id,
				'name' => $place->name,
				'type' => $place->type->code,
				'latitude' => $place->latitude,
				'longitude' => $place->longitude,
				'radius_m' => $place->points->isEmpty() ? $place->effectiveRadius() : null,
				'outline' => $place->points->isEmpty() ? null : $place->points->map(fn (PlacePoint $point) => [$point->latitude, $point->longitude])->all(),
			])->all(),
		]);
	}

	public function types(): JsonResponse
	{
		return response()->json([
			'place_types' => PlaceType::where('active', true)->orderBy('id')->get()
				->map(fn (PlaceType $type) => $type->only(['code', 'label', 'icon', 'color', 'on_map']))->all(),
			'task_types' => TaskType::where('active', true)->orderBy('id')->get()
				->map(fn (TaskType $type) => ['code' => $type->code, 'label' => $type->label, 'app_action' => $type->app_action->value])->all(),
		]);
	}

	private function boundingBox(Request $request): array
	{
		$box = array_map('trim', explode(',', (string) $request->query('bbox')));
		$valid = count($box) === 4 && array_filter($box, 'is_numeric') === $box;
		[$minLng, $minLat, $maxLng, $maxLat] = $valid ? array_map('floatval', $box) : [0.0, 0.0, 0.0, 0.0];

		if (! $valid || $minLng >= $maxLng || $minLat >= $maxLat || $minLat < -90 || $maxLat > 90 || $minLng < -180 || $maxLng > 180) {
			throw ValidationException::withMessages(['bbox' => 'La zone doit être de la forme bbox=ouest,sud,est,nord.']);
		}

		$span = config('positions.map_max_span_degrees');

		if ($maxLng - $minLng > $span || $maxLat - $minLat > $span) {
			throw ValidationException::withMessages(['bbox' => 'Zone trop grande : rapproche-toi sur la carte.']);
		}

		return [$minLng, $minLat, $maxLng, $maxLat];
	}
}
