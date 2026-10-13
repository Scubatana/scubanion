<?php

namespace Tests\Feature\Me;

use App\Models\Activity;
use App\Models\Companion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanionProgressTest extends TestCase
{
	use RefreshDatabase;

	protected function setUp(): void
	{
		parent::setUp();

		$this->travelTo('2026-10-13 15:00:00');
	}

	private function playerSince(string $createdAt): User
	{
		$user = User::factory()->create(['created_at' => $createdAt]);
		Companion::factory()->for($user)->create();
		Sanctum::actingAs($user);

		return $user;
	}

	private function walked(User $user, string $day, int $steps): void
	{
		Activity::factory()->for($user)->create(['day' => $day, 'steps' => $steps, 'goal' => 5000]);
	}

	public function test_a_new_companion_starts_at_level_one_in_full_shape(): void
	{
		$this->playerSince('2026-10-13 09:00:00');

		$this->getJson('/api/me')
			->assertOk()
			->assertJsonPath('companion.level', 1)
			->assertJsonPath('companion.xp', 0)
			->assertJsonPath('companion.xp_for_next_level', 1000)
			->assertJsonPath('companion.mood', 100)
			->assertJsonPath('companion.health', 100);
	}

	public function test_sending_steps_makes_the_companion_level_up(): void
	{
		$user = $this->playerSince('2026-10-01 09:00:00');
		$this->walked($user, '2026-10-11', 5200);
		$this->walked($user, '2026-10-12', 3000);

		$this->getJson('/api/me')->assertJsonPath('companion.level', 1)->assertJsonPath('companion.xp', 520 + 50 + 300);

		$this->postJson('/api/me/steps', ['device_id' => 'phone-aaaa-1111', 'days' => [['day' => '2026-10-13', 'steps' => 2000]]])
			->assertOk()
			->assertJsonPath('companion.level', 2)
			->assertJsonPath('companion.xp', 1070 - 1000)
			->assertJsonPath('companion.xp_for_next_level', 2000);
	}

	public function test_mood_follows_the_best_of_today_and_yesterday(): void
	{
		$user = $this->playerSince('2026-09-01 09:00:00');
		$this->walked($user, '2026-10-12', 2500);

		$this->getJson('/api/me')->assertJsonPath('companion.mood', 50);

		$this->walked($user, '2026-10-13', 5000);

		$this->getJson('/api/me')->assertJsonPath('companion.mood', 100);

		$this->travelTo('2026-10-15 09:00:00');

		$this->getJson('/api/me')->assertJsonPath('companion.mood', 0);
	}

	public function test_health_follows_the_last_seven_days(): void
	{
		$user = $this->playerSince('2026-09-01 09:00:00');
		foreach (['2026-10-06', '2026-10-08', '2026-10-12'] as $day) {
			$this->walked($user, $day, 6000);
		}
		$this->walked($user, '2026-10-09', 2500);
		$this->walked($user, '2026-10-10', 2500);
		$this->walked($user, '2026-10-13', 9000);

		$this->getJson('/api/me')->assertJsonPath('companion.health', (int) round(100 * 4 / 7));
	}

	public function test_days_before_the_account_existed_count_as_reached(): void
	{
		$this->playerSince('2026-10-12 18:00:00');

		$this->getJson('/api/me')
			->assertJsonPath('companion.mood', 0)
			->assertJsonPath('companion.health', (int) round(100 * 6 / 7));
	}

	public function test_changing_the_goal_updates_the_companion(): void
	{
		$user = $this->playerSince('2026-09-01 09:00:00');
		$this->walked($user, '2026-10-13', 6000);

		$this->getJson('/api/me')->assertJsonPath('companion.xp', 600 + 50);

		$this->putJson('/api/me/goal', ['daily_step_goal' => 8000])
			->assertOk()
			->assertJsonPath('companion.xp', 600)
			->assertJsonPath('companion.mood', 75);
	}
}
