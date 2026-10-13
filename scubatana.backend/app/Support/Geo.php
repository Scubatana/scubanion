<?php

namespace App\Support;

class Geo
{
	private const EARTH_RADIUS_METERS = 6371000;

	public static function distanceInMeters(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
	{
		$latitudeDelta = deg2rad($toLatitude - $fromLatitude);
		$longitudeDelta = deg2rad($toLongitude - $fromLongitude);

		$haversine = sin($latitudeDelta / 2) ** 2
			+ cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

		return 2 * self::EARTH_RADIUS_METERS * asin(min(1.0, sqrt($haversine)));
	}

	/**
	 * @param  array<int, array{0: float, 1: float}>  $polygon  sommets [latitude, longitude], dans l'ordre du contour
	 */
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
}
