<?php

namespace Tests\Feature\Me;

use App\Models\Activity;
use App\Models\Companion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShowMeTest extends TestCase
{
	use RefreshDatabase;

	public function test_it_requires_a_token(): void
	{
		$this->getJson('/api/me')->assertUnauthorized();
	}

	public function test_it_returns_no_companion_before_the_profile_step(): void
	{
		Sanctum::actingAs(User::factory()->withoutProfile()->create());

		$this->getJson('/api/me')
			->assertOk()
			->assertJsonPath('player.profile_completed', false)
			->assertJsonPath('companion', null)
			->assertJsonPath('today.steps', 0)
			->assertJsonPath('today.goal', 5000);
	}

	public function test_it_returns_the_player_the_companion_and_the_steps(): void
	{
		$user = User::factory()->create(['pseudo' => 'MarcheurDuSoir']);
		Companion::factory()->for($user)->create(['level' => 2, 'xp' => 340]);
		Activity::factory()->for($user)->create(['steps' => 147]);
		Activity::factory()->for($user)->create(['day' => now()->subDay()->toDateString(), 'steps' => 4673]);
		Activity::factory()->create(['steps' => 9999]);
		Sanctum::actingAs($user);

		$this->getJson('/api/me')
			->assertOk()
			->assertJsonPath('player.pseudo', 'MarcheurDuSoir')
			->assertJsonPath('player.ambition', 'fitness')
			->assertJsonPath('player.profile_completed', true)
			->assertJsonPath('player.stats.total_steps', 4820)
			->assertJsonPath('player.stats.quests_done', 0)
			->assertJsonPath('player.stats.friends_count', 0)
			->assertJsonPath('companion.name', 'Scubanion')
			->assertJsonPath('companion.level', 2)
			->assertJsonPath('companion.xp', 340)
			->assertJsonPath('companion.xp_for_next_level', 2000)
			->assertJsonPath('today.date', now()->toDateString())
			->assertJsonPath('today.steps', 147)
			->assertJsonPath('today.goal', 5000);
	}
}
