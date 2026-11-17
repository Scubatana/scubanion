<?php

namespace App\Support;

class Geo
{
	private const EARTH_RADIUS_METERS = 6371000;

	private const METERS_PER_DEGREE = 111320;

	private const GEOHASH_ALPHABET = '0123456789bcdefghjkmnpqrstuvwxyz';

	public static function distanceInMeters(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
	{
		$latitudeDelta = deg2rad($toLatitude - $fromLatitude);
		$longitudeDelta = deg2rad($toLongitude - $fromLongitude);

		$haversine = sin($latitudeDelta / 2) ** 2
			+ cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

		return 2 * self::EARTH_RADIUS_METERS * asin(min(1.0, sqrt($haversine)));
	}

	public static function insidePolygon(float $latitude, float $longitude, array $polygon): bool
	{
		$inside = false;
		$count = count($polygon);

		for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
			[$latI, $lngI] = $polygon[$i];
			[$latJ, $lngJ] = $polygon[$j];

			if (($latI > $latitude) !== ($latJ > $latitude)
				&& $longitude < ($lngJ - $lngI) * ($latitude - $latI) / ($latJ - $latI) + $lngI) {
				$inside = ! $inside;
			}
		}

		return $inside;
	}

	public static function distanceToOutlineInMeters(float $latitude, float $longitude, array $polygon): float
	{
		$toMeters = fn (array $point): array => [
			($point[1] - $longitude) * self::METERS_PER_DEGREE * cos(deg2rad($latitude)),
			($point[0] - $latitude) * self::METERS_PER_DEGREE,
		];
		$closest = INF;
		$count = count($polygon);

		for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
			$closest = min($closest, self::distanceFromOriginToSegment($toMeters($polygon[$j]), $toMeters($polygon[$i])));
		}

		return $closest;
	}

	public static function boundingBox(float $latitude, float $longitude, float $meters): array
	{
		$latitudeSpan = $meters / self::METERS_PER_DEGREE;
		$longitudeSpan = $meters / (self::METERS_PER_DEGREE * max(0.01, cos(deg2rad($latitude))));

		return [
			'min_lat' => $latitude - $latitudeSpan,
			'max_lat' => $latitude + $latitudeSpan,
			'min_lng' => $longitude - $longitudeSpan,
			'max_lng' => $longitude + $longitudeSpan,
		];
	}

	/**
	 * Geohash du point : une case de la grille mondiale, d'autant plus petite que le hash est long
	 * (6 caractères : environ 1,2 km sur 0,6 km). Sert à garder une zone approximative, jamais le point exact.
	 */
	public static function geohash(float $latitude, float $longitude, int $precision): string
	{
		$bounds = ['longitude' => [-180.0, 180.0], 'latitude' => [-90.0, 90.0]];
		$hash = '';
		$index = 0;
		$bits = 0;
		$onLongitude = true;

		while (strlen($hash) < $precision) {
			$axis = $onLongitude ? 'longitude' : 'latitude';
			$value = $onLongitude ? $longitude : $latitude;
			$middle = ($bounds[$axis][0] + $bounds[$axis][1]) / 2;

			if ($value >= $middle) {
				$index = $index * 2 + 1;
				$bounds[$axis][0] = $middle;
			} else {
				$index *= 2;
				$bounds[$axis][1] = $middle;
			}

			$onLongitude = ! $onLongitude;

			if (++$bits === 5) {
				$hash .= self::GEOHASH_ALPHABET[$index];
				$index = 0;
				$bits = 0;
			}
		}

		return $hash;
	}

	private static function distanceFromOriginToSegment(array $from, array $to): float
	{
		[$fromX, $fromY] = $from;
		[$deltaX, $deltaY] = [$to[0] - $fromX, $to[1] - $fromY];
		$lengthSquared = $deltaX ** 2 + $deltaY ** 2;
		$ratio = $lengthSquared === 0.0 ? 0.0 : max(0.0, min(1.0, -($fromX * $deltaX + $fromY * $deltaY) / $lengthSquared));

		return hypot($fromX + $ratio * $deltaX, $fromY + $ratio * $deltaY);
	}
}
