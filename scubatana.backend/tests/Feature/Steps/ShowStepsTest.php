<?php

namespace Tests\Feature\Steps;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShowStepsTest extends TestCase
{
	use RefreshDatabase;

	protected function setUp(): void
	{
		parent::setUp();

		$this->travelTo('2026-10-13 15:00:00');
	}

	private function walker(array $stepsByDay, string $createdAt = '2026-08-20 10:00:00'): User
	{
		$user = User::factory()->create(['created_at' => $createdAt]);

		foreach ($stepsByDay as $day => $steps) {
			Activity::factory()->for($user)->create(['day' => $day, 'steps' => $steps]);
		}

		Sanctum::actingAs($user);

		return $user;
	}

	public function test_it_requires_a_token(): void
	{
		$this->getJson('/api/me/steps')->assertUnauthorized();
	}

	public function test_the_current_month_stops_at_today_and_fills_days_without_steps(): void
	{
		$this->walker(['2026-10-02' => 6200, '2026-10-13' => 1500]);

		$response = $this->getJson('/api/me/steps')->assertOk()
			->assertJsonPath('month', '2026-10')
			->assertJsonPath('goal', 5000)
			->assertJsonPath('has_next', false)
			->assertJsonPath('has_previous', true)
			->assertJsonCount(13, 'days')
			->assertJsonPath('days.0', ['day' => '2026-10-01', 'steps' => 0, 'goal' => 5000, 'goal_reached' => false])
			->assertJsonPath('days.1', ['day' => '2026-10-02', 'steps' => 6200, 'goal' => 5000, 'goal_reached' => true])
			->assertJsonPath('days.12', ['day' => '2026-10-13', 'steps' => 1500, 'goal' => 5000, 'goal_reached' => false]);

		$this->assertSame('2026-10-13', collect($response->json('days'))->last()['day']);
	}

	public function test_it_computes_the_stats_of_the_month(): void
	{
		$this->walker(['2026-10-01' => 5000, '2026-10-02' => 8000, '2026-10-03' => 5500, '2026-10-05' => 300, '2026-10-12' => 7000, '2026-10-13' => 200, '2026-09-30' => 20000]);

		$this->getJson('/api/me/steps')->assertOk()
			->assertJsonPath('stats.total', 26000)
			->assertJsonPath('stats.average', 2000)
			->assertJsonPath('stats.active_days', 6)
			->assertJsonPath('stats.goal_days', 4)
			->assertJsonPath('stats.best', ['day' => '2026-10-02', 'steps' => 8000])
			->assertJsonPath('stats.longest_streak', 3);
	}

	public function test_a_past_month_is_complete_and_can_move_forward(): void
	{
		$this->walker(['2026-09-30' => 20000, '2026-10-01' => 5000]);

		$this->getJson('/api/me/steps?month=2026-09')->assertOk()
			->assertJsonPath('month', '2026-09')
			->assertJsonPath('has_next', true)
			->assertJsonCount(30, 'days')
			->assertJsonPath('stats.total', 20000)
			->assertJsonPath('stats.average', 667)
			->assertJsonPath('stats.best.day', '2026-09-30');
	}

	public function test_a_month_without_any_step_has_empty_stats(): void
	{
		$this->walker([]);

		$this->getJson('/api/me/steps?month=2026-08')->assertOk()
			->assertJsonCount(31, 'days')
			->assertJsonPath('stats.total', 0)
			->assertJsonPath('stats.average', 0)
			->assertJsonPath('stats.active_days', 0)
			->assertJsonPath('stats.goal_days', 0)
			->assertJsonPath('stats.best', null)
			->assertJsonPath('stats.longest_streak', 0);
	}

	public function test_it_only_shows_the_steps_of_the_current_player(): void
	{
		Activity::factory()->create(['day' => '2026-10-10', 'steps' => 9999]);
		$this->walker(['2026-10-10' => 100]);

		$this->getJson('/api/me/steps')->assertOk()->assertJsonPath('stats.total', 100);
	}

	public function test_it_rejects_a_future_or_malformed_month(): void
	{
		$this->walker([]);

		$this->getJson('/api/me/steps?month=2026-11')->assertUnprocessable()->assertJsonValidationErrors('month');
		$this->getJson('/api/me/steps?month=octobre')->assertUnprocessable()->assertJsonValidationErrors('month');
	}

	public function test_the_history_never_goes_before_the_month_the_account_was_created(): void
	{
		$this->walker([], '2026-09-18 09:00:00');

		$this->getJson('/api/me/steps?month=2026-09')->assertOk()->assertJsonPath('has_previous', false)->assertJsonPath('has_next', true);
		$this->getJson('/api/me/steps?month=2026-08')->assertUnprocessable()->assertJsonValidationErrors('month');
	}

	public function test_a_streak_stays_alive_but_unlit_until_the_goal_of_the_day_is_reached(): void
	{
		$user = $this->walker(['2026-10-09' => 9000, '2026-10-11' => 5000, '2026-10-12' => 6100, '2026-10-13' => 1200]);

		$this->getJson('/api/me')->assertOk()->assertJsonPath('streak', ['days' => 2, 'active' => false]);

		$user->activities()->whereDate('day', '2026-10-13')->update(['steps' => 5200]);

		$this->getJson('/api/me')->assertOk()->assertJsonPath('streak', ['days' => 3, 'active' => true]);
		$this->getJson('/api/me/steps')->assertOk()->assertJsonPath('streak', ['days' => 3, 'active' => true]);
	}

	public function test_a_streak_is_lost_after_a_full_day_under_the_goal(): void
	{
		$this->walker(['2026-10-09' => 9000, '2026-10-10' => 8000, '2026-10-11' => 7000]);

		$this->getJson('/api/me')->assertOk()->assertJsonPath('streak', ['days' => 0, 'active' => false]);
	}
}
