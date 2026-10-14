<?php

namespace Tests\Feature\Friends;

use App\Models\Activity;
use App\Models\Companion;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FriendsTest extends TestCase
{
	use RefreshDatabase;

	private User $lea;

	private User $yanis;

	protected function setUp(): void
	{
		parent::setUp();

		$this->travelTo('2026-10-13 15:00:00');
		$this->lea = User::factory()->create(['pseudo' => 'Lea']);
		$this->yanis = User::factory()->create(['pseudo' => 'Yanis']);
	}

	private function as(User $player): static
	{
		Sanctum::actingAs($player);

		return $this;
	}

	private function invite(User $from, User $to)
	{
		return $this->as($from)->postJson('/api/me/friends', ['code' => $to->friend_code]);
	}

	public function test_every_player_gets_a_unique_friend_code(): void
	{
		$this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $this->lea->friend_code);
		$this->assertNotSame($this->lea->friend_code, $this->yanis->friend_code);
	}

	public function test_it_requires_a_token(): void
	{
		$this->getJson('/api/me/friends')->assertUnauthorized();
		$this->postJson('/api/me/friends', ['code' => 'ABCDEFGH'])->assertUnauthorized();
	}

	public function test_an_invitation_shows_on_both_sides_until_it_is_accepted(): void
	{
		$this->invite($this->lea, $this->yanis)
			->assertOk()
			->assertJsonPath('code', $this->lea->friend_code)
			->assertJsonPath('friends', [])
			->assertJsonPath('sent.0.player', ['id' => $this->yanis->id, 'pseudo' => 'Yanis']);

		$this->as($this->yanis)->getJson('/api/me/friends')
			->assertOk()
			->assertJsonPath('received.0.player', ['id' => $this->lea->id, 'pseudo' => 'Lea'])
			->assertJsonPath('sent', []);
	}

	public function test_the_code_can_be_typed_in_lowercase_with_spaces(): void
	{
		$typed = strtolower(substr($this->yanis->friend_code, 0, 4).' '.substr($this->yanis->friend_code, 4));

		$this->as($this->lea)->postJson('/api/me/friends', ['code' => $typed])->assertOk()->assertJsonCount(1, 'sent');
	}

	public function test_it_refuses_an_unknown_code_its_own_code_and_a_player_without_profile(): void
	{
		$withoutProfile = User::factory()->withoutProfile()->create();
		$this->as($this->lea);

		$this->postJson('/api/me/friends', ['code' => 'ZZZZZZZZ'])->assertUnprocessable()->assertJsonValidationErrors('code');
		$this->postJson('/api/me/friends', ['code' => $this->lea->friend_code])->assertUnprocessable()->assertJsonValidationErrors('code');
		$this->postJson('/api/me/friends', ['code' => $withoutProfile->friend_code])->assertUnprocessable()->assertJsonValidationErrors('code');
		$this->postJson('/api/me/friends', [])->assertUnprocessable()->assertJsonValidationErrors('code');
		$this->assertSame(0, Friendship::count());
	}

	public function test_a_player_without_profile_cannot_invite(): void
	{
		$this->invite(User::factory()->withoutProfile()->create(), $this->lea)->assertStatus(409);
	}

	public function test_it_refuses_a_second_invitation_and_an_invitation_between_friends(): void
	{
		$this->invite($this->lea, $this->yanis)->assertOk();
		$this->invite($this->lea, $this->yanis)->assertUnprocessable()->assertJsonValidationErrors(['code' => 'Invitation déjà envoyée.']);

		$friendship = Friendship::sole();
		$this->as($this->yanis)->postJson("/api/me/friends/{$friendship->id}/accept")->assertOk();

		$this->invite($this->yanis, $this->lea)->assertUnprocessable()->assertJsonValidationErrors(['code' => 'Vous êtes déjà amis.']);
	}

	public function test_inviting_someone_who_already_invited_you_makes_you_friends(): void
	{
		$this->invite($this->lea, $this->yanis)->assertOk();

		$this->invite($this->yanis, $this->lea)
			->assertOk()
			->assertJsonPath('friends.0.player.pseudo', 'Lea')
			->assertJsonPath('received', []);

		$this->assertSame(1, Friendship::count());
	}

	public function test_friends_see_each_other_s_companion_and_steps_of_the_day(): void
	{
		Companion::factory()->for($this->yanis)->create(['name' => 'Bulle', 'level' => 3]);
		Activity::factory()->for($this->yanis)->create(['day' => '2026-10-13', 'steps' => 4200]);
		$this->invite($this->lea, $this->yanis);

		$this->as($this->yanis)->postJson('/api/me/friends/'.Friendship::sole()->id.'/accept')
			->assertOk()
			->assertJsonPath('friends.0.player.pseudo', 'Lea')
			->assertJsonPath('friends.0.since', '2026-10-13');

		$this->as($this->lea)->getJson('/api/me/friends')
			->assertOk()
			->assertJsonPath('friends.0.player.id', $this->yanis->id)
			->assertJsonPath('friends.0.player.companion.name', 'Bulle')
			->assertJsonPath('friends.0.player.companion.level', 3)
			->assertJsonPath('friends.0.player.today.steps', 4200)
			->assertJsonPath('friends.0.player.today.goal', 5000);

		$this->getJson('/api/me')->assertJsonPath('player.stats.friends_count', 1);
	}

	public function test_only_the_invited_player_can_accept(): void
	{
		$this->invite($this->lea, $this->yanis);
		$friendship = Friendship::sole();

		$this->as($this->lea)->postJson("/api/me/friends/{$friendship->id}/accept")->assertStatus(409);
		$this->as(User::factory()->create())->postJson("/api/me/friends/{$friendship->id}/accept")->assertNotFound();
	}

	public function test_an_invitation_can_be_declined_or_cancelled_and_a_friend_removed(): void
	{
		$this->invite($this->lea, $this->yanis);
		$this->as($this->yanis)->deleteJson('/api/me/friends/'.Friendship::sole()->id)->assertOk()->assertJsonPath('received', []);

		$this->invite($this->lea, $this->yanis);
		$this->as($this->lea)->deleteJson('/api/me/friends/'.Friendship::sole()->id)->assertOk()->assertJsonPath('sent', []);

		$this->invite($this->lea, $this->yanis);
		$friendship = Friendship::sole();
		$this->as($this->yanis)->postJson("/api/me/friends/{$friendship->id}/accept")->assertOk();
		$this->as(User::factory()->create())->deleteJson("/api/me/friends/{$friendship->id}")->assertNotFound();
		$this->as($this->lea)->deleteJson("/api/me/friends/{$friendship->id}")->assertOk()->assertJsonPath('friends', []);

		$this->assertSame(0, Friendship::count());
	}

	public function test_a_friendship_goes_away_with_either_player(): void
	{
		$this->invite($this->lea, $this->yanis);

		$this->yanis->delete();

		$this->assertSame(0, Friendship::count());
	}
}
