<?php

namespace App\Models;

use App\Support\Geo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[Fillable(['place_type_id', 'name', 'latitude', 'longitude', 'radius_m', 'min_lat', 'max_lat', 'min_lng', 'max_lng', 'attributes', 'source', 'external_id', 'synced_at', 'active'])]
class Place extends Model
{
	use HasFactory;

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

	/**
	 * @param  array<int, array{0: float, 1: float}>  $outline  sommets [latitude, longitude], dans l'ordre du contour
	 */
	public function setOutline(array $outline): void
	{
		if (count($outline) < 3) {
			throw new InvalidArgumentException('An outline needs at least three points.');
		}

		$this->points()->delete();

		foreach (array_values($outline) as $position => [$latitude, $longitude]) {
			$this->points()->create(['position' => $position, 'latitude' => $latitude, 'longitude' => $longitude]);
		}

		$this->update([
			'min_lat' => min(array_column($outline, 0)),
			'max_lat' => max(array_column($outline, 0)),
			'min_lng' => min(array_column($outline, 1)),
			'max_lng' => max(array_column($outline, 1)),
		]);
	}

	public function contains(float $latitude, float $longitude): bool
	{
		$points = $this->points;

		if ($points->isEmpty()) {
			$radius = $this->radius_m ?? config('places.default_radius_m');

			return Geo::distanceInMeters($latitude, $longitude, $this->latitude, $this->longitude) <= $radius;
		}

		if ($latitude < $this->min_lat || $latitude > $this->max_lat || $longitude < $this->min_lng || $longitude > $this->max_lng) {
			return false;
		}

		return Geo::insidePolygon($latitude, $longitude, $points->map(fn (PlacePoint $point) => [$point->latitude, $point->longitude])->all());
	}
}
