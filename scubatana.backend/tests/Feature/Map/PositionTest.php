<?php

namespace Tests\Feature\Map;

use App\Models\QuestBan;
use App\Models\User;
use App\Models\UserPosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PositionTest extends TestCase
{
	use RefreshDatabase;

	private User $lea;

	protected function setUp(): void
	{
		parent::setUp();

		$this->travelTo('2026-10-14 15:00:00');
		$this->lea = User::factory()->create();
		Sanctum::actingAs($this->lea);
	}

	private function send(float $latitude, float $longitude, array $extra = [])
	{
		return $this->postJson('/api/me/position', ['latitude' => $latitude, 'longitude' => $longitude, ...$extra]);
	}

	public function test_it_keeps_a_single_last_known_position(): void
	{
		$this->send(43.6953, 7.2806, ['accuracy_m' => 12])->assertNoContent();
		$this->travel(1)->minutes();
		$this->send(43.6960, 7.2810)->assertNoContent();

		$position = UserPosition::sole();
		$this->assertSame([43.696, 7.281], [$position->latitude, $position->longitude]);
		$this->assertSame('2026-10-14 15:01:00', $position->recorded_at->toDateTimeString());
		$this->assertSame(0, QuestBan::count());
	}

	public function test_an_older_position_never_replaces_a_newer_one(): void
	{
		$this->send(43.6953, 7.2806);
		$this->send(43.70, 7.25, ['recorded_at' => '2026-10-14T14:50:00+02:00'])->assertNoContent();

		$this->assertSame(43.6953, UserPosition::sole()->latitude);
	}

	public function test_moving_faster_than_a_plane_blocks_location_tasks_for_a_day_without_saying_so(): void
	{
		$this->send(43.6953, 7.2806);
		$this->travel(1)->minutes();

		$this->send(48.8566, 2.3522)->assertNoContent();

		$ban = QuestBan::sole();
		$this->assertSame('teleport', $ban->reason);
		$this->assertSame('2026-10-15 15:01:00', $ban->ends_at->toDateTimeString());
		$this->assertGreaterThan(500000, $ban->evidence['distance_m']);
		$this->assertTrue($this->lea->isBannedFromLocationTasks());
	}

	public function test_a_long_trip_at_a_plausible_speed_is_not_blocked(): void
	{
		$this->send(43.6953, 7.2806);
		$this->travel(2)->hours();

		$this->send(43.5528, 7.0174);
		$this->send(43.5530, 7.0180);

		$this->assertSame(0, QuestBan::count());
	}

	public function test_a_mocked_position_blocks_and_a_repeated_offence_extends_the_same_ban(): void
	{
		$this->send(43.6953, 7.2806, ['mocked' => true]);
		$this->travel(3)->hours();
		$this->send(43.6953, 7.2806, ['mocked' => true]);

		$ban = QuestBan::sole();
		$this->assertSame('mocked', $ban->reason);
		$this->assertSame('2026-10-15 18:00:00', $ban->ends_at->toDateTimeString());
	}

	public function test_it_validates_the_position(): void
	{
		$this->postJson('/api/me/position', [])->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
		$this->send(95, 7.2)->assertUnprocessable()->assertJsonValidationErrors('latitude');
		$this->send(43.6, 7.2, ['recorded_at' => '2026-10-14T16:00:00+02:00'])->assertUnprocessable()->assertJsonValidationErrors('recorded_at');
	}
}
