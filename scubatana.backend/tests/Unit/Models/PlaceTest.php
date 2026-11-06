<?php

namespace Tests\Unit\Models;

use App\Models\Place;
use App\Models\PlaceType;
use App\Models\TaskType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PlaceTest extends TestCase
{
	use RefreshDatabase;

	public function test_the_types_of_places_and_tasks_are_data_shipped_with_the_schema(): void
	{
		$this->assertEqualsCanonicalizing(['park', 'point_of_interest', 'partner', 'bus_stop', 'zone'], PlaceType::pluck('code')->all());
		$this->assertFalse(PlaceType::where('code', 'zone')->value('on_map'));
		$this->assertEqualsCanonicalizing(['auto', 'checkin', 'scan', 'answer', 'meet'], TaskType::all()->map(fn (TaskType $type) => $type->app_action->value)->unique()->values()->all());

		PlaceType::create(['code' => 'forest', 'label' => 'Forêt']);

		$this->assertTrue(PlaceType::where('code', 'forest')->value('active'));
	}

	public function test_a_point_place_is_checked_by_its_radius(): void
	{
		$castle = Place::factory()->create(['latitude' => 43.6953, 'longitude' => 7.2806]);
		$this->assertTrue($castle->contains(43.6960, 7.2806));
		$this->assertFalse($castle->contains(43.6966, 7.2806));

		$castle->update(['radius_m' => 200]);
		$this->assertTrue($castle->fresh()->contains(43.6966, 7.2806));
	}

	public function test_a_point_place_gets_a_bounding_box_from_its_radius(): void
	{
		$castle = Place::factory()->create(['latitude' => 43.6953, 'longitude' => 7.2806, 'radius_m' => 100]);

		$this->assertEqualsWithDelta(43.6953 - 100 / 111320, $castle->min_lat, 0.000001);
		$this->assertEqualsWithDelta(43.6953 + 100 / 111320, $castle->max_lat, 0.000001);
		$this->assertLessThan(7.2806, $castle->min_lng);
		$this->assertGreaterThan(7.2806, $castle->max_lng);

		$castle->update(['radius_m' => 300]);

		$this->assertEqualsWithDelta(43.6953 + 300 / 111320, $castle->fresh()->max_lat, 0.000001);
	}

	public function test_a_point_place_keeps_the_radius_it_was_created_with(): void
	{
		$stop = Place::factory()->ofType('bus_stop')->create(['latitude' => 43.6953, 'longitude' => 7.2806]);

		$this->assertSame(100, $stop->radius_m);

		config(['places.default_radius_m' => 300]);

		$this->assertTrue($stop->fresh()->contains(43.6961, 7.2806));
		$this->assertFalse($stop->fresh()->contains(43.6966, 7.2806));
		$this->assertTrue(Place::reaching(43.6961, 7.2806, 0)->whereKey($stop->id)->exists());
		$this->assertFalse(Place::reaching(43.6966, 7.2806, 0)->whereKey($stop->id)->exists());
	}

	public function test_drawing_an_outline_drops_the_radius(): void
	{
		$park = Place::factory()->create(['latitude' => 43.65, 'longitude' => 7.25]);

		$park->setOutline([[43.70, 7.20], [43.70, 7.30], [43.60, 7.30]]);

		$this->assertNull($park->fresh()->radius_m);
	}

	public function test_the_gps_accuracy_widens_a_point_place_up_to_a_limit(): void
	{
		$castle = Place::factory()->create(['latitude' => 43.6953, 'longitude' => 7.2806, 'radius_m' => 100]);

		$this->assertFalse($castle->contains(43.6966, 7.2806));
		$this->assertFalse($castle->contains(43.6966, 7.2806, 30));
		$this->assertTrue($castle->contains(43.6966, 7.2806, 50));
		$this->assertTrue($castle->contains(43.6966, 7.2806, 1000000));
		$this->assertFalse($castle->contains(43.6970, 7.2806, 1000000));
	}

	public function test_the_gps_accuracy_widens_an_outline_up_to_a_limit(): void
	{
		$forest = Place::factory()->ofType('zone')->create(['latitude' => 43.65, 'longitude' => 7.25]);
		$forest->setOutline([[43.70, 7.20], [43.70, 7.30], [43.60, 7.30], [43.60, 7.20]]);
		$forest = $forest->fresh();

		$this->assertFalse($forest->contains(43.7003, 7.25));
		$this->assertFalse($forest->contains(43.7003, 7.25, 20));
		$this->assertTrue($forest->contains(43.7003, 7.25, 40));
		$this->assertFalse($forest->contains(43.7010, 7.25, 1000000));
	}

	public function test_a_large_area_is_checked_by_its_outline(): void
	{
		$forest = Place::factory()->ofType('zone')->create(['latitude' => 43.65, 'longitude' => 7.25]);

		$forest->setOutline([[43.70, 7.20], [43.70, 7.30], [43.60, 7.30], [43.60, 7.20]]);
		$forest = $forest->fresh();

		$this->assertSame([43.6, 43.7, 7.2, 7.3], [$forest->min_lat, $forest->max_lat, $forest->min_lng, $forest->max_lng]);
		$this->assertCount(4, $forest->points);
		$this->assertTrue($forest->contains(43.61, 7.29));
		$this->assertFalse($forest->contains(43.71, 7.25));

		$this->expectException(InvalidArgumentException::class);
		$forest->setOutline([[43.70, 7.20], [43.70, 7.30]]);
	}

	public function test_the_distance_outside_a_place_counts_from_its_edge_minus_the_gps_margin(): void
	{
		$castle = Place::factory()->create(['latitude' => 43.6953, 'longitude' => 7.2806, 'radius_m' => 100]);

		$this->assertSame(0.0, $castle->distanceOutside(43.6960, 7.2806));
		$this->assertEqualsWithDelta(133.5, $castle->distanceOutside(43.6974, 7.2806), 1);
		$this->assertEqualsWithDelta(103.5, $castle->distanceOutside(43.6974, 7.2806, 30), 1);

		$forest = Place::factory()->ofType('zone')->create(['latitude' => 43.65, 'longitude' => 7.25]);
		$forest->setOutline([[43.70, 7.20], [43.70, 7.30], [43.60, 7.30], [43.60, 7.20]]);
		$forest = $forest->fresh();

		$this->assertSame(0.0, $forest->distanceOutside(43.61, 7.29));
		$this->assertEqualsWithDelta(111.3, $forest->distanceOutside(43.7010, 7.25), 1);
		$this->assertEqualsWithDelta(71.3, $forest->distanceOutside(43.7010, 7.25, 40), 1);
	}

	public function test_it_keeps_free_attributes_and_an_external_identifier(): void
	{
		$stop = Place::factory()->ofType('bus_stop')->create(['attributes' => ['lines' => ['15', '25']], 'source' => 'gtfs', 'external_id' => 'NICE:1234']);

		$this->assertSame(['lines' => ['15', '25']], $stop->fresh()->attributes);
		$this->assertSame('bus_stop', $stop->type->code);

		$this->expectException(QueryException::class);
		Place::factory()->create(['source' => 'gtfs', 'external_id' => 'NICE:1234']);
	}

	public function test_a_type_in_use_cannot_be_deleted(): void
	{
		Place::factory()->create();

		$this->expectException(QueryException::class);
		PlaceType::where('code', 'park')->delete();
	}
}
