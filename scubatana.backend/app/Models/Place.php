<?php

namespace App\Models;

use App\Support\Geo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

#[Fillable(['place_type_id', 'name', 'latitude', 'longitude', 'radius_m', 'min_lat', 'max_lat', 'min_lng', 'max_lng', 'attributes', 'source', 'external_id', 'synced_at', 'active'])]
class Place extends Model
{
	use HasFactory;

	protected static function booted(): void
	{
		static::saving(function (Place $place): void {
			if (! $place->exists || ! $place->points()->exists()) {
				$place->radius_m ??= config('places.default_radius_m');
				$place->fill(Geo::boundingBox($place->latitude, $place->longitude, $place->radius_m));
			}
		});
	}

	protected function casts(): array
	{
		return [
			'latitude' => 'float',
			'longitude' => 'float',
			'radius_m' => 'integer',
			'min_lat' => 'float',
			'max_lat' => 'float',
			'min_lng' => 'float',
			'max_lng' => 'float',
			'attributes' => 'array',
			'synced_at' => 'datetime',
			'active' => 'boolean',
		];
	}

	public function type(): BelongsTo
	{
		return $this->belongsTo(PlaceType::class, 'place_type_id');
	}

	public function points(): HasMany
	{
		return $this->hasMany(PlacePoint::class)->orderBy('position');
	}

	public static function accuracyMargin(?int $accuracy): int
	{
		return min(max(0, $accuracy ?? 0), config('places.max_accuracy_margin_m'));
	}

	public function setOutline(array $outline): void
	{
		if (count($outline) < 3) {
			throw new InvalidArgumentException('An outline needs at least three points.');
		}

		DB::transaction(function () use ($outline): void {
			$this->points()->delete();

			foreach (array_values($outline) as $position => [$latitude, $longitude]) {
				$this->points()->create(['position' => $position, 'latitude' => $latitude, 'longitude' => $longitude]);
			}

			$this->update([
				'radius_m' => null,
				'min_lat' => min(array_column($outline, 0)),
				'max_lat' => max(array_column($outline, 0)),
				'min_lng' => min(array_column($outline, 1)),
				'max_lng' => max(array_column($outline, 1)),
			]);
		});
	}

	public function effectiveRadius(): int
	{
		return $this->radius_m ?? config('places.default_radius_m');
	}

	public function contains(float $latitude, float $longitude, ?int $accuracy = null): bool
	{
		$margin = self::accuracyMargin($accuracy);
		$points = $this->points;

		if ($points->isEmpty()) {
			return Geo::distanceInMeters($latitude, $longitude, $this->latitude, $this->longitude) <= $this->effectiveRadius() + $margin;
		}

		$reach = Geo::boundingBox($latitude, $longitude, $margin);

		if ($reach['max_lat'] < $this->min_lat || $reach['min_lat'] > $this->max_lat || $reach['max_lng'] < $this->min_lng || $reach['min_lng'] > $this->max_lng) {
			return false;
		}

		$outline = $points->map(fn (PlacePoint $point) => [$point->latitude, $point->longitude])->all();

		return Geo::insidePolygon($latitude, $longitude, $outline)
			|| Geo::distanceToOutlineInMeters($latitude, $longitude, $outline) <= $margin;
	}

	public function distanceOutside(float $latitude, float $longitude, ?int $accuracy = null): float
	{
		$margin = self::accuracyMargin($accuracy);
		$points = $this->points;

		if ($points->isEmpty()) {
			return max(0.0, Geo::distanceInMeters($latitude, $longitude, $this->latitude, $this->longitude) - $this->effectiveRadius() - $margin);
		}

		$outline = $points->map(fn (PlacePoint $point) => [$point->latitude, $point->longitude])->all();

		if (Geo::insidePolygon($latitude, $longitude, $outline)) {
			return 0.0;
		}

		return max(0.0, Geo::distanceToOutlineInMeters($latitude, $longitude, $outline) - $margin);
	}

	#[Scope]
	protected function reaching(Builder $query, float $latitude, float $longitude, int $margin): void
	{
		$reach = Geo::boundingBox($latitude, $longitude, $margin);

		$query
			->where('min_lat', '<=', $reach['max_lat'])->where('max_lat', '>=', $reach['min_lat'])
			->where('min_lng', '<=', $reach['max_lng'])->where('max_lng', '>=', $reach['min_lng']);
	}
}
