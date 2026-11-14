<?php

namespace Tests\Feature\Friends;

use App\Enums\FriendshipStatus;
use App\Models\Activity;
use App\Models\Cheer;
use App\Models\Companion;
use App\Models\Friendship;
use App\Models\User;
use App\Services\CheerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheersTest extends TestCase
{
	use RefreshDatabase;

	private User $lea;

	private User $yanis;

	private Friendship $friendship;

	protected function setUp(): void
	{
		parent::setUp();

		$this->travelTo('2026-10-13 15:00:00');
		$this->lea = User::factory()->create(['pseudo' => 'Lea', 'created_at' => '2026-09-01 09:00:00']);
		$this->yanis = User::factory()->create(['pseudo' => 'Yanis', 'created_at' => '2026-09-01 09:00:00']);
		$this->friendship = Friendship::create([
			'user_id' => $this->lea->id,
			'friend_id' => $this->yanis->id,
			'status' => FriendshipStatus::Accepted,
			'accepted_at' => now(),
		]);
	}

	private function as(User $player): static
	{
		Sanctum::actingAs($player);

		return $this;
	}

	private function cheer(User $from, ?Friendship $friendship = null)
	{
		return $this->as($from)->postJson('/api/me/friends/'.($friendship ?? $this->friendship)->id.'/cheer');
	}

	public function test_it_requires_a_token(): void
	{
		$this->postJson("/api/me/friends/{$this->friendship->id}/cheer")->assertUnauthorized();
	}

	public function test_a_cheer_shows_as_sent_for_one_and_received_for_the_other(): void
	{
		$this->cheer($this->lea)
			->assertOk()
			->assertJsonPath('friends.0.cheer', ['sent' => true, 'received' => false]);

		$this->as($this->yanis)->getJson('/api/me/friends')
			->assertOk()
			->assertJsonPath('friends.0.cheer', ['sent' => false, 'received' => true]);
	}

	public function test_both_friends_can_cheer_each_other_the_same_day(): void
	{
		$this->cheer($this->lea)->assertOk();

		$this->cheer($this->yanis)
			->assertOk()
			->assertJsonPath('friends.0.cheer', ['sent' => true, 'received' => true]);
	}

	public function test_only_one_cheer_per_friend_and_per_day(): void
	{
		$this->cheer($this->lea)->assertOk();

		$this->cheer($this->lea)
			->assertStatus(409)
			->assertJsonPath('message', "Tu as déjà envoyé un coup de patte à Yanis aujourd'hui.");

		$this->assertSame(1, Cheer::count());
	}

	public function test_a_new_day_starts_at_midnight_in_paris(): void
	{
		$this->cheer($this->lea)->assertOk();

		$this->travelTo('2026-10-13 23:30:00');
		$this->cheer($this->lea)->assertStatus(409);

		$this->travelTo('2026-10-14 00:05:00');
		$this->cheer($this->lea)->assertOk();
		$this->as($this->lea)->getJson('/api/me/friends')->assertJsonPath('friends.0.cheer.sent', true);
	}

	public function test_yesterdays_cheers_no_longer_show(): void
	{
		$this->cheer($this->lea)->assertOk();
		$this->travelTo('2026-10-14 09:00:00');

		$this->as($this->yanis)->getJson('/api/me/friends')->assertJsonPath('friends.0.cheer', ['sent' => false, 'received' => false]);
	}

	public function test_an_invitation_cannot_be_cheered(): void
	{
		$tom = User::factory()->create(['pseudo' => 'Tom']);
		$pending = Friendship::create(['user_id' => $this->lea->id, 'friend_id' => $tom->id, 'status' => FriendshipStatus::Pending]);

		$this->cheer($this->lea, $pending)->assertStatus(409);
		$this->assertSame(0, Cheer::count());
	}

	public function test_a_stranger_cannot_cheer_someone_elses_friendship(): void
	{
		$tom = User::factory()->create(['pseudo' => 'Tom']);

		$this->cheer($tom)->assertNotFound();
		$this->assertSame(0, Cheer::count());
	}

	public function test_received_cheers_lift_the_companion_mood_up_to_a_daily_cap(): void
	{
		Companion::factory()->for($this->yanis)->create();
		Activity::factory()->for($this->yanis)->create(['day' => '2026-10-13', 'steps' => 2500, 'goal' => 5000]);
		$friends = User::factory()->count(4)->create(['created_at' => '2026-09-01 09:00:00']);

		$this->as($this->yanis)->getJson('/api/me')->assertJsonPath('companion.mood', 50);

		$this->cheer($this->lea)->assertOk();
		$this->as($this->yanis)->getJson('/api/me')->assertJsonPath('companion.mood', 50 + CheerService::MOOD_BONUS);

		foreach ($friends as $friend) {
			Cheer::create(['user_id' => $friend->id, 'friend_id' => $this->yanis->id, 'day' => '2026-10-13']);
		}

		$this->as($this->yanis)->getJson('/api/me')->assertJsonPath('companion.mood', 50 + CheerService::MOOD_BONUS_MAX);
	}

	public function test_the_mood_bonus_never_goes_past_100(): void
	{
		Companion::factory()->for($this->yanis)->create();
		Activity::factory()->for($this->yanis)->create(['day' => '2026-10-13', 'steps' => 5000, 'goal' => 5000]);

		$this->cheer($this->lea)->assertOk();

		$this->as($this->yanis)->getJson('/api/me')->assertJsonPath('companion.mood', 100);
	}

	public function test_cheers_are_deleted_with_the_account(): void
	{
		$this->cheer($this->lea)->assertOk();
		$this->cheer($this->yanis)->assertOk();

		$this->lea->delete();

		$this->assertSame(0, Cheer::count());
	}
}
