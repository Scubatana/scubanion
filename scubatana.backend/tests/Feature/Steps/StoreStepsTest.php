<?php

namespace Tests\Feature\Steps;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreStepsTest extends TestCase
{
	use RefreshDatabase;

	protected function setUp(): void
	{
		parent::setUp();

		$this->travelTo('2026-10-13 15:00:00');
	}

	private function send(array $days, string $deviceId = 'phone-aaaa-1111')
	{
		return $this->postJson('/api/me/steps', ['device_id' => $deviceId, 'days' => $days]);
	}

	public function test_it_requires_a_token(): void
	{
		$this->send([['day' => '2026-10-13', 'steps' => 100]])->assertUnauthorized();
	}

	public function test_it_stores_the_total_of_the_day_and_returns_it(): void
	{
		$user = User::factory()->create();
		Sanctum::actingAs($user);

		$this->send([['day' => '2026-10-13', 'steps' => 3200]])
			->assertOk()
			->assertJsonPath('today.date', '2026-10-13')
			->assertJsonPath('today.steps', 3200)
			->assertJsonPath('today.goal', 5000)
			->assertJsonPath('total_steps', 3200);

		$this->assertSame(1, Activity::count());
		$this->getJson('/api/me')->assertJsonPath('today.steps', 3200)->assertJsonPath('player.stats.total_steps', 3200);
	}

	public function test_sending_the_same_total_twice_counts_it_once(): void
	{
		Sanctum::actingAs(User::factory()->create());

		$this->send([['day' => '2026-10-13', 'steps' => 3200]])->assertOk();
		$this->send([['day' => '2026-10-13', 'steps' => 3200]])->assertOk()->assertJsonPath('today.steps', 3200);

		$this->assertSame(1, Activity::count());
	}

	public function test_a_lower_total_never_erases_steps_already_counted(): void
	{
		Sanctum::actingAs(User::factory()->create());

		$this->send([['day' => '2026-10-13', 'steps' => 3200]])->assertOk();
		$this->send([['day' => '2026-10-13', 'steps' => 150]])->assertOk()->assertJsonPath('today.steps', 3200);
	}

	public function test_it_stores_several_days_at_once_for_a_phone_that_was_offline(): void
	{
		Sanctum::actingAs(User::factory()->create());

		$this->send([['day' => '2026-10-12', 'steps' => 7400], ['day' => '2026-10-13', 'steps' => 900]])
			->assertOk()
			->assertJsonPath('today.steps', 900)
			->assertJsonPath('total_steps', 8300);
	}

	public function test_steps_gained_faster_than_a_human_can_walk_are_capped(): void
	{
		Sanctum::actingAs(User::factory()->create());

		$this->send([['day' => '2026-10-13', 'steps' => 2000]])->assertOk();
		$this->travel(60)->seconds();

		$this->send([['day' => '2026-10-13', 'steps' => 50000]])->assertOk()->assertJsonPath('today.steps', 2200);
	}

	public function test_capped_steps_are_recovered_once_enough_time_has_passed(): void
	{
		Sanctum::actingAs(User::factory()->create());

		$this->send([['day' => '2026-10-13', 'steps' => 2000]])->assertOk();
		$this->travel(60)->seconds();
		$this->send([['day' => '2026-10-13', 'steps' => 2600]])->assertOk()->assertJsonPath('today.steps', 2200);
		$this->travel(5)->minutes();

		$this->send([['day' => '2026-10-13', 'steps' => 2600]])->assertOk()->assertJsonPath('today.steps', 2600);
	}

	public function test_a_first_total_is_capped_by_the_time_elapsed_since_midnight(): void
	{
		$this->travelTo('2026-10-13 00:30:00');
		Sanctum::actingAs(User::factory()->create());

		$this->send([['day' => '2026-10-13', 'steps' => 90000]])->assertOk()->assertJsonPath('today.steps', 6000);
	}

	public function test_a_day_never_exceeds_the_daily_ceiling(): void
	{
		$this->travelTo('2026-10-13 23:59:00');
		Sanctum::actingAs(User::factory()->create());

		$this->send([['day' => '2026-10-13', 'steps' => 5000000]])->assertOk()->assertJsonPath('today.steps', 100000);
	}

	public function test_players_never_share_their_steps(): void
	{
		$other = User::factory()->create();
		Activity::factory()->for($other)->create(['day' => '2026-10-13', 'steps' => 9000]);
		Sanctum::actingAs(User::factory()->create());

		$this->send([['day' => '2026-10-13', 'steps' => 1200]])->assertOk()->assertJsonPath('today.steps', 1200)->assertJsonPath('total_steps', 1200);

		$this->assertSame(9000, $other->activities()->sum('steps'));
	}

	public function test_it_rejects_invalid_entries(): void
	{
		Sanctum::actingAs(User::factory()->create());

		$this->send([])->assertUnprocessable()->assertJsonValidationErrors('days');
		$this->send([['day' => '13/10/2026', 'steps' => 10]])->assertUnprocessable()->assertJsonValidationErrors('days.0.day');
		$this->send([['day' => '2026-10-13', 'steps' => -5]])->assertUnprocessable()->assertJsonValidationErrors('days.0.steps');
		$this->send([['day' => '2026-10-13', 'steps' => 'beaucoup']])->assertUnprocessable()->assertJsonValidationErrors('days.0.steps');
		$this->send([['day' => '2026-10-13']])->assertUnprocessable()->assertJsonValidationErrors('days.0.steps');
		$this->send([['day' => '2026-10-13', 'steps' => 10], ['day' => '2026-10-13', 'steps' => 20]])->assertUnprocessable();
	}

	public function test_it_rejects_days_too_old_or_in_the_future(): void
	{
		Sanctum::actingAs(User::factory()->create());

		$this->send([['day' => '2026-09-12', 'steps' => 10]])->assertUnprocessable()->assertJsonValidationErrors('days.0.day');
		$this->send([['day' => '2026-10-15', 'steps' => 10]])->assertUnprocessable()->assertJsonValidationErrors('days.0.day');
		$this->send([['day' => '2026-09-13', 'steps' => 10]])->assertOk();
	}

	public function test_steps_from_two_phones_are_added_up_and_each_phone_stays_idempotent(): void
	{
		Sanctum::actingAs(User::factory()->create());

		$this->send([['day' => '2026-10-13', 'steps' => 3000]], 'phone-aaaa-1111')->assertOk();
		$this->send([['day' => '2026-10-13', 'steps' => 1500]], 'phone-bbbb-2222')->assertOk()->assertJsonPath('today.steps', 4500);
		$this->send([['day' => '2026-10-13', 'steps' => 1500]], 'phone-bbbb-2222')->assertOk()->assertJsonPath('today.steps', 4500);
	}

	public function test_it_requires_a_device_identifier(): void
	{
		Sanctum::actingAs(User::factory()->create());

		$this->postJson('/api/me/steps', ['days' => [['day' => '2026-10-13', 'steps' => 10]]])->assertUnprocessable()->assertJsonValidationErrors('device_id');
	}
}
