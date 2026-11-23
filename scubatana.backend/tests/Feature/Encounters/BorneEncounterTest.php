<?php

namespace Tests\Feature\Encounters;

use App\Models\Block;
use App\Models\Encounter;
use App\Models\Place;
use App\Models\QuestBan;
use App\Models\Terminal;
use App\Models\User;
use App\Services\EncounterService;
use App\Services\TerminalDisplay;
use App\Services\TerminalTokenService;
use App\Support\Geo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class BorneEncounterTest extends TestCase
{
	use RefreshDatabase;

	private Terminal $terminal;

	private User $lea;

	private User $yanis;

	protected function setUp(): void
	{
		parent::setUp();

		$this->travelTo('2026-11-17 15:00:00');
		$this->terminal = Terminal::factory()->create(['code' => 'chateau-01']);
		$this->lea = User::factory()->create(['pseudo' => 'Lea']);
		$this->yanis = User::factory()->create(['pseudo' => 'Yanis']);
		$this->mock(TerminalDisplay::class)->shouldReceive('show');
	}

	private function pass(User $player, string $scannedAt, array $position = [])
	{
		Sanctum::actingAs($player);
		$token = app(TerminalTokenService::class)->generate($this->terminal, now()->parse($scannedAt));

		return $this->postJson('/api/me/passages', ['token' => $token, ...$position])->assertOk();
	}

	public function test_two_players_passing_at_the_same_borne_within_ten_minutes_meet(): void
	{
		$this->pass($this->lea, '2026-11-17 15:00:00');
		$this->assertSame(0, Encounter::count());

		$this->travel(8)->minutes();
		$this->pass($this->yanis, '2026-11-17 15:08:00');

		$encounter = Encounter::sole();
		$this->assertSame([$this->lea->id, $this->yanis->id], [$encounter->user_id, $encounter->other_user_id]);
		$this->assertSame('2026-11-17 15:08:00', $encounter->met_at->toDateTimeString());
		$this->assertTrue($encounter->isValidated());
		$this->assertNull($encounter->zone);
	}

	public function test_passages_more_than_ten_minutes_apart_do_not_count(): void
	{
		$this->pass($this->lea, '2026-11-17 15:00:00');
		$this->travel(11)->minutes();
		$this->pass($this->yanis, '2026-11-17 15:11:00');

		$this->assertSame(0, Encounter::count());
	}

	public function test_a_passage_sent_later_from_offline_still_meets_at_the_time_of_the_scan(): void
	{
		$this->pass($this->lea, '2026-11-17 15:00:00');
		$this->travel(3)->hours();

		$this->pass($this->yanis, '2026-11-17 15:05:00');

		$this->assertSame('2026-11-17 15:05:00', Encounter::sole()->met_at->toDateTimeString());
	}

	public function test_the_zone_is_the_area_of_the_place_of_the_borne(): void
	{
		$castle = Place::factory()->create(['latitude' => 43.6953, 'longitude' => 7.2806, 'radius_m' => 100]);
		$this->terminal->update(['place_id' => $castle->id]);
		$here = ['latitude' => 43.6953, 'longitude' => 7.2806, 'accuracy_m' => 10];

		$this->pass($this->lea, '2026-11-17 15:00:00', $here);
		$this->pass($this->yanis, '2026-11-17 15:02:00', $here);

		$this->assertSame(Geo::geohash(43.6953, 7.2806, 6), Encounter::sole()->zone);
	}

	public function test_several_passages_the_same_day_make_a_single_encounter(): void
	{
		$this->pass($this->lea, '2026-11-17 15:00:00');
		$this->pass($this->yanis, '2026-11-17 15:01:00');
		$this->travel(30)->minutes();
		$this->pass($this->lea, '2026-11-17 15:30:00');
		$this->pass($this->yanis, '2026-11-17 15:31:00');

		$this->assertSame(1, Encounter::count());
	}

	public function test_blocked_players_do_not_meet_at_a_borne(): void
	{
		Block::create(['user_id' => $this->yanis->id, 'blocked_id' => $this->lea->id]);

		$this->pass($this->lea, '2026-11-17 15:00:00');
		$this->pass($this->yanis, '2026-11-17 15:01:00');

		$this->assertSame(0, Encounter::count());
	}

	public function test_a_player_does_not_meet_themselves_at_a_borne(): void
	{
		$this->pass($this->lea, '2026-11-17 15:00:00');
		$this->pass($this->lea, '2026-11-17 15:01:00');

		$this->assertSame(0, Encounter::count());
	}

	public function test_a_player_blocked_for_cheating_meets_nobody_at_a_borne(): void
	{
		QuestBan::factory()->create(['user_id' => $this->yanis->id]);

		$this->pass($this->lea, '2026-11-17 15:00:00');
		$this->pass($this->yanis, '2026-11-17 15:01:00');
		$this->pass($this->lea, '2026-11-17 15:02:00');

		$this->assertSame(0, Encounter::count());
	}

	public function test_a_failure_while_meeting_does_not_fail_the_passage(): void
	{
		Exceptions::fake();
		$this->mock(EncounterService::class)->shouldReceive('meetAtTerminal')->andThrow(new RuntimeException('base indisponible'));

		$this->pass($this->lea, '2026-11-17 15:00:00')->assertJsonPath('status', 'rewarded');

		Exceptions::assertReported(RuntimeException::class);
	}
}
