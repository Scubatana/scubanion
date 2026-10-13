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

	public function test_it_measures_the_distance_to_the_closest_edge_of_an_outline(): void
	{
		$square = [[43.70, 7.20], [43.70, 7.30], [43.60, 7.30], [43.60, 7.20]];

		$this->assertEqualsWithDelta(33.4, Geo::distanceToOutlineInMeters(43.7003, 7.25, $square), 0.5);
		$this->assertEqualsWithDelta(33.4, Geo::distanceToOutlineInMeters(43.6997, 7.25, $square), 0.5);
		$this->assertEqualsWithDelta(Geo::distanceInMeters(43.7010, 7.31, 43.70, 7.30), Geo::distanceToOutlineInMeters(43.7010, 7.31, $square), 2);
	}

	public function test_it_draws_a_bounding_box_around_a_point(): void
	{
		$box = Geo::boundingBox(43.0, 7.0, 1113.2);

		$this->assertEqualsWithDelta(42.99, $box['min_lat'], 0.000001);
		$this->assertEqualsWithDelta(43.01, $box['max_lat'], 0.000001);
		$this->assertEqualsWithDelta(0.01 / cos(deg2rad(43.0)), $box['max_lng'] - 7.0, 0.000001);
	}
}
