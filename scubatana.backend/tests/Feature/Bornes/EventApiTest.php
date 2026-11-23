<?php

namespace Tests\Feature\Bornes;

use App\Models\Accessory;
use App\Models\Event;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventApiTest extends TestCase
{
	use RefreshDatabase;

	public function test_it_requires_a_token(): void
	{
		$this->getJson('/api/events')->assertUnauthorized();
	}

	public function test_it_lists_the_running_then_upcoming_events_with_their_terminals_and_reward(): void
	{
		$this->travelTo('2026-10-18 12:00:00');
		$crown = Accessory::where('code', 'crown')->sole();
		$upcoming = Event::factory()->between('2026-10-31 00:00:00', '2026-11-02 00:00:00')->create(['name' => 'Halloween', 'description' => 'Un chapeau à gagner', 'bonus_xp' => 0, 'accessory_id' => $crown->id, 'all_terminals' => false]);
		$upcoming->terminals()->attach([
			Terminal::factory()->create(['code' => 'parc-01', 'name' => 'Borne du parc'])->id,
			Terminal::factory()->create(['code' => 'chateau-01', 'name' => 'Borne du Château'])->id,
		]);
		$running = Event::factory()->between('2026-10-17 00:00:00', '2026-10-19 00:00:00')->create(['name' => 'Week-end au parc', 'bonus_xp' => 50]);
		Event::factory()->between('2026-10-10 00:00:00', '2026-10-12 00:00:00')->create();
		Event::factory()->between('2026-10-17 00:00:00', '2026-10-19 00:00:00')->create(['active' => false]);
		Sanctum::actingAs(User::factory()->create());

		$this->getJson('/api/events')
			->assertOk()
			->assertExactJson(['events' => [
				[
					'id' => $running->id,
					'name' => 'Week-end au parc',
					'description' => null,
					'starts_at' => '2026-10-17T00:00:00+02:00',
					'ends_at' => '2026-10-19T00:00:00+02:00',
					'in_progress' => true,
					'bonus_xp' => 50,
					'accessory' => null,
					'all_terminals' => true,
					'terminals' => [],
				],
				[
					'id' => $upcoming->id,
					'name' => 'Halloween',
					'description' => 'Un chapeau à gagner',
					'starts_at' => '2026-10-31T00:00:00+01:00',
					'ends_at' => '2026-11-02T00:00:00+01:00',
					'in_progress' => false,
					'bonus_xp' => 0,
					'accessory' => ['id' => 'crown', 'label' => 'Couronne'],
					'all_terminals' => false,
					'terminals' => [
						['code' => 'chateau-01', 'name' => 'Borne du Château'],
						['code' => 'parc-01', 'name' => 'Borne du parc'],
					],
				],
			]]);
	}

	public function test_a_withdrawn_accessory_is_not_announced(): void
	{
		$crown = Accessory::where('code', 'crown')->sole();
		Event::factory()->create(['accessory_id' => $crown->id]);
		$crown->update(['active' => false]);
		Sanctum::actingAs(User::factory()->create());

		$this->getJson('/api/events')->assertOk()->assertJsonPath('events.0.accessory', null);
	}

	public function test_an_event_is_gone_from_the_list_once_it_ends(): void
	{
		$this->travelTo('2026-10-18 23:59:59');
		Event::factory()->between('2026-10-17 00:00:00', '2026-10-19 00:00:00')->create();
		Sanctum::actingAs(User::factory()->create());

		$this->getJson('/api/events')->assertOk()->assertJsonCount(1, 'events');

		$this->travelTo('2026-10-19 00:00:00');

		$this->getJson('/api/events')->assertOk()->assertJsonCount(0, 'events');
	}
}
