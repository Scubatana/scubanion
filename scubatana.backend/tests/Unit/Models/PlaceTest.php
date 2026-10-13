<?php

namespace Tests\Unit\Models;

use App\Enums\PlaceType;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceTest extends TestCase
{
	use RefreshDatabase;

	public function test_it_reads_back_the_type_and_the_coordinates(): void
	{
		$place = Place::factory()->create([
			'name' => 'Colline du Château',
			'type' => PlaceType::PointOfInterest,
			'latitude' => 43.695123,
			'longitude' => 7.281456,
		])->fresh();

		$this->assertSame('Colline du Château', $place->name);
		$this->assertSame(PlaceType::PointOfInterest, $place->type);
		$this->assertSame(43.695123, $place->latitude);
		$this->assertSame(7.281456, $place->longitude);
	}
}
