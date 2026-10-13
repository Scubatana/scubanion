<?php

namespace Tests\Feature\Steps;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateGoalTest extends TestCase
{
	use RefreshDatabase;

	protected function setUp(): void
	{
		parent::setUp();

		$this->travelTo('2026-10-13 15:00:00');
	}

	public function test_it_requires_a_token(): void
	{
		$this->putJson('/api/me/goal', ['daily_step_goal' => 8000])->assertUnauthorized();
	}

	public function test_a_new_goal_applies_to_today_and_leaves_past_days_as_they_were(): void
	{
		$user = User::factory()->create(['created_at' => '2026-09-01 10:00:00']);
		Activity::factory()->for($user)->create(['day' => '2026-10-11', 'steps' => 5200, 'goal' => 5000]);
		Activity::factory()->for($user)->create(['day' => '2026-10-12', 'steps' => 5600, 'goal' => 5000]);
		Activity::factory()->for($user)->create(['day' => '2026-10-13', 'steps' => 6000, 'goal' => 5000]);
		Sanctum::actingAs($user);

		$this->getJson('/api/me')->assertJsonPath('streak', ['days' => 3, 'active' => true]);

		$this->putJson('/api/me/goal', ['daily_step_goal' => 8000])
			->assertOk()
			->assertJsonPath('today.goal', 8000)
			->assertJsonPath('streak', ['days' => 2, 'active' => false]);

		$this->getJson('/api/me/steps')->assertOk()
			->assertJsonPath('goal', 8000)
			->assertJsonPath('days.10', ['day' => '2026-10-11', 'steps' => 5200, 'goal' => 5000, 'goal_reached' => true])
			->assertJsonPath('days.12', ['day' => '2026-10-13', 'steps' => 6000, 'goal' => 8000, 'goal_reached' => false])
			->assertJsonPath('stats.goal_days', 2);
	}

	public function test_it_rejects_a_goal_out_of_bounds(): void
	{
		Sanctum::actingAs(User::factory()->create());

		$this->putJson('/api/me/goal', ['daily_step_goal' => 500])->assertUnprocessable()->assertJsonValidationErrors('daily_step_goal');
		$this->putJson('/api/me/goal', ['daily_step_goal' => 60000])->assertUnprocessable()->assertJsonValidationErrors('daily_step_goal');
		$this->putJson('/api/me/goal', [])->assertUnprocessable()->assertJsonValidationErrors('daily_step_goal');
	}
}
