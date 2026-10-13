<?php

namespace Tests\Feature\Map;

use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MapTest extends TestCase
{
	use RefreshDatabase;

	private const NICE = '7.20,43.65,7.35,43.75';

	protected function setUp(): void
	{
		parent::setUp();

		Sanctum::actingAs(User::factory()->create());
	}

	public function test_it_sends_the_places_of_the_area_shown_on_the_map(): void
	{
		$castle = Place::factory()->ofType('point_of_interest')->create(['name' => 'Colline du Château', 'latitude' => 43.6953, 'longitude' => 7.2806, 'radius_m' => 150]);
		Place::factory()->create(['name' => 'Parc à Paris', 'latitude' => 48.85, 'longitude' => 2.35]);
		Place::factory()->create(['name' => 'Parc fermé', 'latitude' => 43.70, 'longitude' => 7.27, 'active' => false]);
		Place::factory()->ofType('zone')->create(['name' => 'Zone de ciblage', 'latitude' => 43.70, 'longitude' => 7.27]);
		$forestType = PlaceType::create(['code' => 'forest', 'label' => 'Forêt']);
		$forest = Place::factory()->create(['place_type_id' => $forestType->id, 'name' => 'Forêt', 'latitude' => 43.80, 'longitude' => 7.25]);
		$forest->setOutline([[43.76, 7.24], [43.74, 7.26], [43.84, 7.26]]);

		$this->getJson('/api/map?bbox='.self::NICE)
			->assertOk()
			->assertJsonCount(2, 'places')
			->assertJsonPath('places.0', ['id' => $castle->id, 'name' => 'Colline du Château', 'type' => 'point_of_interest', 'latitude' => 43.6953, 'longitude' => 7.2806, 'radius_m' => 150, 'outline' => null])
			->assertJsonPath('places.1.name', 'Forêt')
			->assertJsonPath('places.1.radius_m', null)
			->assertJsonPath('places.1.outline', [[43.76, 7.24], [43.74, 7.26], [43.84, 7.26]]);
	}

	public function test_a_crowded_area_keeps_the_places_closest_to_its_centre_and_says_so(): void
	{
		config(['positions.map_max_places' => 2]);
		Place::factory()->create(['name' => 'Bord', 'latitude' => 43.66, 'longitude' => 7.21]);
		Place::factory()->create(['name' => 'Centre', 'latitude' => 43.70, 'longitude' => 7.275]);
		Place::factory()->create(['name' => 'Près du centre', 'latitude' => 43.71, 'longitude' => 7.28]);

		$this->getJson('/api/map?bbox='.self::NICE)
			->assertOk()
			->assertJsonPath('truncated', true)
			->assertJsonPath('places.0.name', 'Centre')
			->assertJsonPath('places.1.name', 'Près du centre');

		config(['positions.map_max_places' => 3]);

		$this->getJson('/api/map?bbox='.self::NICE)->assertJsonPath('truncated', false)->assertJsonCount(3, 'places');
	}

	public function test_the_area_must_be_valid_and_not_too_large(): void
	{
		$this->getJson('/api/map')->assertUnprocessable()->assertJsonValidationErrors('bbox');
		$this->getJson('/api/map?bbox=7.35,43.65,7.20,43.75')->assertUnprocessable()->assertJsonValidationErrors('bbox');
		$this->getJson('/api/map?bbox=a,b,c,d')->assertUnprocessable()->assertJsonValidationErrors('bbox');
		$this->getJson('/api/map?bbox=6.0,43.0,8.0,44.0')->assertUnprocessable()->assertJsonValidationErrors('bbox');
	}

	public function test_the_catalog_of_types_can_be_cached_with_an_etag(): void
	{
		$response = $this->getJson('/api/catalog/types')
			->assertOk()
			->assertJsonPath('place_types.0', ['code' => 'park', 'label' => 'Parc', 'icon' => 'tree', 'color' => '#4FA871', 'on_map' => true])
			->assertJsonPath('task_types.0', ['code' => 'steps', 'label' => 'Marcher', 'app_action' => 'auto']);
		$etag = $response->headers->get('ETag');

		$this->assertNotEmpty($etag);
		$this->getJson('/api/catalog/types', ['If-None-Match' => $etag])->assertStatus(304);

		PlaceType::create(['code' => 'forest', 'label' => 'Forêt']);
		$this->getJson('/api/catalog/types', ['If-None-Match' => $etag])->assertOk()->assertJsonCount(6, 'place_types');
	}

	public function test_it_requires_a_token(): void
	{
		$this->app['auth']->forgetGuards();

		$this->withHeader('Authorization', 'Bearer faux')->getJson('/api/map?bbox='.self::NICE)->assertUnauthorized();
	}
}
