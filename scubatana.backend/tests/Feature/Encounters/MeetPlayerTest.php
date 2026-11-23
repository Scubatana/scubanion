<?php

namespace Tests\Feature\Encounters;

use App\Models\Block;
use App\Models\Encounter;
use App\Models\Friendship;
use App\Models\Quest;
use App\Models\QuestBan;
use App\Models\QuestTask;
use App\Models\TaskType;
use App\Models\User;
use App\Models\UserPosition;
use App\Models\UserQuest;
use App\Support\Geo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeetPlayerTest extends TestCase
{
	use RefreshDatabase;

	private const CASTLE_LATITUDE = 43.6953;

	private const CASTLE_LONGITUDE = 7.2806;

	private const TOO_FAR = "Vous semblez trop loin l'un de l'autre : rapprochez-vous, puis scanne à nouveau.";

	private const INVALID = "Ce QR code n'est plus valable. Demande à l'autre joueur de l'afficher à nouveau.";

	private User $lea;

	private User $yanis;

	protected function setUp(): void
	{
		parent::setUp();

		$this->travelTo('2026-11-17 16:00:00');
		$this->lea = User::factory()->create(['pseudo' => 'Lea']);
		$this->yanis = User::factory()->create(['pseudo' => 'Yanis']);
	}

	private function at(User $player, float $latitude, float $longitude, int $minutesAgo = 0, int $accuracy = 10): void
	{
		UserPosition::updateOrCreate(
			['user_id' => $player->id],
			['latitude' => $latitude, 'longitude' => $longitude, 'accuracy_m' => $accuracy, 'mocked' => false, 'recorded_at' => now()->subMinutes($minutesAgo)],
		);
	}

	private function bothAtTheCastle(): void
	{
		$this->at($this->lea, self::CASTLE_LATITUDE, self::CASTLE_LONGITUDE, 2);
		$this->at($this->yanis, self::CASTLE_LATITUDE, self::CASTLE_LONGITUDE + 0.0005, 1);
	}

	private function qrOf(User $player): string
	{
		Sanctum::actingAs($player);

		return $this->getJson('/api/me/encounters/qr')->assertOk()->json('token');
	}

	private function meet(User $scanner, string $token, array $position = [])
	{
		Sanctum::actingAs($scanner);

		return $this->postJson('/api/me/encounters', ['token' => $token, ...$position]);
	}

	public function test_it_requires_a_session(): void
	{
		$this->postJson('/api/me/encounters', ['token' => 'x'])->assertUnauthorized();
	}

	public function test_two_players_side_by_side_meet_by_scanning_a_qr(): void
	{
		$this->bothAtTheCastle();

		$this->meet($this->yanis, $this->qrOf($this->lea))
			->assertCreated()
			->assertExactJson(['player' => ['pseudo' => 'Lea'], 'met_at' => '2026-11-17T16:00:00+01:00', 'new' => true]);

		$encounter = Encounter::sole();
		$this->assertSame([$this->lea->id, $this->yanis->id], [$encounter->user_id, $encounter->other_user_id]);
		$this->assertTrue($encounter->isValidated());
		$this->assertSame(Geo::geohash(self::CASTLE_LATITUDE, self::CASTLE_LONGITUDE + 0.0005, 6), $encounter->zone);
		$this->assertSame(6, strlen($encounter->zone));
	}

	public function test_the_same_pair_meets_once_a_day(): void
	{
		$this->bothAtTheCastle();
		$this->meet($this->yanis, $this->qrOf($this->lea))->assertCreated();

		$this->meet($this->lea, $this->qrOf($this->yanis))
			->assertOk()
			->assertJsonPath('player.pseudo', 'Yanis')
			->assertJsonPath('new', false);
		$this->assertSame(1, Encounter::count());

		$this->travel(1)->days();
		$this->bothAtTheCastle();
		$this->meet($this->yanis, $this->qrOf($this->lea))->assertCreated();
		$this->assertSame(2, Encounter::count());
	}

	public function test_the_position_sent_with_the_scan_is_recorded_and_used(): void
	{
		$this->at($this->lea, self::CASTLE_LATITUDE, self::CASTLE_LONGITUDE, 3);

		$this->meet($this->yanis, $this->qrOf($this->lea), ['latitude' => self::CASTLE_LATITUDE, 'longitude' => self::CASTLE_LONGITUDE + 0.0003, 'accuracy_m' => 8.4])
			->assertCreated();

		$position = $this->yanis->position()->sole();
		$this->assertSame(self::CASTLE_LONGITUDE + 0.0003, $position->longitude);
		$this->assertSame(8, $position->accuracy_m);
	}

	public function test_the_scanner_needs_a_recent_position(): void
	{
		$this->at($this->lea, self::CASTLE_LATITUDE, self::CASTLE_LONGITUDE);
		$this->at($this->yanis, self::CASTLE_LATITUDE, self::CASTLE_LONGITUDE, 11);

		$this->meet($this->yanis, $this->qrOf($this->lea))
			->assertUnprocessable()
			->assertJsonValidationErrors(['position' => 'Active ta localisation pour valider la rencontre, puis scanne à nouveau.']);
		$this->assertSame(0, Encounter::count());
	}

	public function test_the_shown_player_needs_a_recent_position(): void
	{
		$this->at($this->yanis, self::CASTLE_LATITUDE, self::CASTLE_LONGITUDE);

		$this->meet($this->yanis, $this->qrOf($this->lea))
			->assertUnprocessable()
			->assertJsonValidationErrors(['position' => "Demande à Lea d'ouvrir Scubanion avec sa localisation activée, puis scanne à nouveau."]);

		$this->at($this->lea, self::CASTLE_LATITUDE, self::CASTLE_LONGITUDE, 11);

		$this->meet($this->yanis, $this->qrOf($this->lea))->assertJsonValidationErrors('position');
		$this->assertSame(0, Encounter::count());
	}

	public function test_players_too_far_apart_do_not_meet(): void
	{
		$this->at($this->lea, self::CASTLE_LATITUDE, self::CASTLE_LONGITUDE);
		$this->at($this->yanis, 43.6956, 7.2686);

		$this->meet($this->yanis, $this->qrOf($this->lea))
			->assertUnprocessable()
			->assertJsonValidationErrors(['position' => self::TOO_FAR]);
		$this->assertSame(0, Encounter::count());
	}

	public function test_the_gps_accuracy_widens_the_distance_up_to_a_cap(): void
	{
		$this->at($this->lea, self::CASTLE_LATITUDE, self::CASTLE_LONGITUDE, 0, 50);
		$this->at($this->yanis, self::CASTLE_LATITUDE + 0.00162, self::CASTLE_LONGITUDE, 0, 50);

		$this->meet($this->yanis, $this->qrOf($this->lea))->assertCreated();

		Encounter::query()->delete();
		$this->at($this->lea, self::CASTLE_LATITUDE, self::CASTLE_LONGITUDE, 0, 500);
		$this->at($this->yanis, self::CASTLE_LATITUDE + 0.00225, self::CASTLE_LONGITUDE, 0, 500);

		$this->meet($this->yanis, $this->qrOf($this->lea))->assertJsonValidationErrors(['position' => self::TOO_FAR]);
	}

	public function test_a_simulated_position_prevents_the_encounter_and_blocks_the_scanner(): void
	{
		$this->at($this->lea, self::CASTLE_LATITUDE, self::CASTLE_LONGITUDE);

		$this->meet($this->yanis, $this->qrOf($this->lea), ['latitude' => self::CASTLE_LATITUDE, 'longitude' => self::CASTLE_LONGITUDE, 'mocked' => true])
			->assertJsonValidationErrors(['position' => self::TOO_FAR]);

		$this->assertSame(0, Encounter::count());
		$this->assertTrue($this->yanis->fresh()->isBannedFromLocationTasks());
	}

	public function test_a_player_blocked_for_cheating_cannot_meet(): void
	{
		$this->bothAtTheCastle();
		QuestBan::factory()->create(['user_id' => $this->lea->id]);

		$this->meet($this->yanis, $this->qrOf($this->lea))->assertJsonValidationErrors(['position' => self::TOO_FAR]);
		$this->assertSame(0, Encounter::count());
	}

	public function test_an_invalid_or_expired_qr_is_refused(): void
	{
		$this->bothAtTheCastle();

		$this->meet($this->yanis, 'faux')->assertJsonValidationErrors(['token' => self::INVALID]);

		$token = $this->qrOf($this->lea);
		$this->travel(61)->seconds();

		$this->meet($this->yanis, $token)->assertJsonValidationErrors(['token' => self::INVALID]);
		$this->meet($this->yanis, '')->assertJsonValidationErrors(['token' => "Scanne le QR code de l'autre joueur."]);
		$this->assertSame(0, Encounter::count());
	}

	public function test_a_player_cannot_meet_themselves(): void
	{
		$this->bothAtTheCastle();

		$this->meet($this->lea, $this->qrOf($this->lea))
			->assertJsonValidationErrors(['token' => "C'est ton propre QR code : fais-le scanner par l'autre joueur."]);
	}

	public function test_blocked_players_cannot_meet_and_are_not_told_why(): void
	{
		$this->bothAtTheCastle();
		Block::create(['user_id' => $this->lea->id, 'blocked_id' => $this->yanis->id]);

		$this->meet($this->yanis, $this->qrOf($this->lea))->assertJsonValidationErrors(['token' => self::INVALID]);
		$this->meet($this->lea, $this->qrOf($this->yanis))->assertJsonValidationErrors(['token' => self::INVALID]);
		$this->assertSame(0, Encounter::count());
	}

	public function test_a_complete_profile_is_required(): void
	{
		$this->bothAtTheCastle();
		$token = $this->qrOf($this->lea);
		$this->yanis->update(['pseudo' => null]);

		$this->meet($this->yanis, $token)->assertStatus(409);
	}

	public function test_a_meeting_advances_the_meet_task_of_both_players(): void
	{
		$quest = Quest::factory()->create(['name' => 'Rencontre', 'auto_assign' => true]);
		QuestTask::factory()->for($quest)->create(['step' => 1, 'position' => 1, 'task_type_id' => TaskType::byCode('meet_player')->id, 'description' => 'Croise un autre joueur', 'target' => 1]);

		foreach ([$this->lea, $this->yanis] as $player) {
			Sanctum::actingAs($player);
			$this->getJson('/api/me/quests')->assertOk()->assertJsonPath('quests.0.tasks.0.value', 0);
		}

		$this->bothAtTheCastle();
		$this->meet($this->yanis, $this->qrOf($this->lea))->assertCreated();

		$this->assertSame(2, UserQuest::whereNotNull('completed_at')->count());
	}

	public function test_the_meeting_qr_lasts_a_minute_and_needs_a_profile(): void
	{
		Sanctum::actingAs($this->lea);
		$this->getJson('/api/me/encounters/qr')->assertOk()->assertJsonPath('expires_at', '2026-11-17T16:01:00+01:00');

		$this->lea->update(['pseudo' => null]);
		$this->getJson('/api/me/encounters/qr')->assertStatus(409);
	}

	public function test_showing_a_meeting_qr_does_not_make_friends(): void
	{
		$this->bothAtTheCastle();
		$meetingQr = $this->qrOf($this->lea);

		Sanctum::actingAs($this->yanis);
		$this->postJson('/api/me/friends/qr', ['token' => $meetingQr])
			->assertJsonValidationErrors(['token' => "Ce QR code n'est plus valable. Demande à ton ami de l'afficher à nouveau."]);

		$this->assertSame(0, Friendship::count());
	}

	public function test_a_friend_qr_does_not_validate_an_encounter(): void
	{
		$this->bothAtTheCastle();
		Sanctum::actingAs($this->lea);
		$friendQr = $this->getJson('/api/me/friends/qr')->assertOk()->json('token');

		$this->meet($this->yanis, $friendQr)->assertJsonValidationErrors(['token' => self::INVALID]);
		$this->assertSame(0, Encounter::count());
	}
}
