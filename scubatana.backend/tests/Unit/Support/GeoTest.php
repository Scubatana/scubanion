<?php

namespace Tests\Unit\Support;

use App\Support\Geo;
use PHPUnit\Framework\TestCase;

class GeoTest extends TestCase
{
	public function test_it_measures_the_distance_between_two_points(): void
	{
		$this->assertSame(0.0, Geo::distanceInMeters(43.6953, 7.2806, 43.6953, 7.2806));
		$this->assertEqualsWithDelta(111195, Geo::distanceInMeters(43.0, 7.0, 44.0, 7.0), 1);
		$this->assertEqualsWithDelta(966, Geo::distanceInMeters(43.6953, 7.2806, 43.6956, 7.2686), 5);
	}

	public function test_it_tells_whether_a_point_is_inside_a_polygon(): void
	{
		$square = [[43.70, 7.20], [43.70, 7.30], [43.60, 7.30], [43.60, 7.20]];
		$elbow = [[0.0, 0.0], [0.0, 4.0], [2.0, 4.0], [2.0, 2.0], [4.0, 2.0], [4.0, 0.0]];

		$this->assertTrue(Geo::insidePolygon(43.65, 7.25, $square));
		$this->assertFalse(Geo::insidePolygon(43.75, 7.25, $square));
		$this->assertTrue(Geo::insidePolygon(1.0, 3.0, $elbow));
		$this->assertTrue(Geo::insidePolygon(3.0, 1.0, $elbow));
		$this->assertFalse(Geo::insidePolygon(3.0, 3.0, $elbow));
	}
}
