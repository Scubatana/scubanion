<?php

namespace Tests\Feature\Bornes;

use App\Enums\TerminalStatus;
use App\Models\Place;
use App\Models\Terminal;
use App\Models\User;
use App\Services\BadgeTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TerminalApiTest extends TestCase
{
	use RefreshDatabase;

	public function test_it_requires_a_token(): void
	{
		$this->getJson('/api/me/badge')->assertUnauthorized();
		$this->getJson('/api/bornes')->assertUnauthorized();
	}

	public function test_a_player_gets_a_short_lived_badge_for_the_terminals(): void
	{
		$this->travelTo('2026-10-13 15:00:00');
		$player = User::factory()->create();
		Sanctum::actingAs($player);

		$response = $this->getJson('/api/me/badge')->assertOk()->assertJsonPath('expires_at', '2026-10-13T15:05:00+02:00');

		$this->assertTrue(app(BadgeTokenService::class)->verify($response->json('token'), now())->is($player));
	}

	public function test_it_lists_the_terminals_with_their_place_and_whether_they_are_online(): void
	{
		$this->travelTo('2026-10-13 15:00:00');
		$place = Place::factory()->create(['name' => 'Colline du Château', 'latitude' => 43.695, 'longitude' => 7.281]);
		Terminal::factory()->create(['code' => 'chateau-01', 'name' => 'Borne du Château', 'place_id' => $place->id, 'status' => TerminalStatus::Online, 'last_seen_at' => now()->subSeconds(30)]);
		Terminal::factory()->create(['code' => 'atelier-01', 'name' => 'Atelier', 'status' => TerminalStatus::Online, 'last_seen_at' => now()->subMinutes(10)]);
		Sanctum::actingAs(User::factory()->create());

		$this->getJson('/api/bornes')
			->assertOk()
			->assertJsonPath('terminals.0.code', 'atelier-01')
			->assertJsonPath('terminals.0.online', false)
			->assertJsonPath('terminals.0.place', null)
			->assertJsonPath('terminals.1.code', 'chateau-01')
			->assertJsonPath('terminals.1.online', true)
			->assertJsonPath('terminals.1.place.name', 'Colline du Château')
			->assertJsonPath('terminals.1.place.latitude', 43.695);
	}
}
