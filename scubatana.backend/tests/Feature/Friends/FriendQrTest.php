<?php

namespace Tests\Feature\Friends;

use App\Enums\FriendshipStatus;
use App\Models\Block;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FriendQrTest extends TestCase
{
	use RefreshDatabase;

	private const INVALID = "Ce QR code n'est plus valable. Demande à ton ami de l'afficher à nouveau.";

	private User $lea;

	private User $yanis;

	protected function setUp(): void
	{
		parent::setUp();

		$this->travelTo('2026-11-09 16:00:00');
		$this->lea = User::factory()->create(['pseudo' => 'Lea']);
		$this->yanis = User::factory()->create(['pseudo' => 'Yanis']);
	}

	private function qrOf(User $player): string
	{
		Sanctum::actingAs($player);

		return $this->getJson('/api/me/friends/qr')->assertOk()->json('token');
	}

	private function scan(User $scanner, string $token)
	{
		Sanctum::actingAs($scanner);

		return $this->postJson('/api/me/friends/qr', ['token' => $token]);
	}

	public function test_it_requires_a_token(): void
	{
		$this->getJson('/api/me/friends/qr')->assertUnauthorized();
		$this->postJson('/api/me/friends/qr', ['token' => 'x'])->assertUnauthorized();
	}

	public function test_a_player_gets_a_qr_token_valid_for_a_minute(): void
	{
		Sanctum::actingAs($this->lea);

		$this->getJson('/api/me/friends/qr')
			->assertOk()
			->assertJsonStructure(['token', 'expires_at'])
			->assertJsonPath('expires_at', '2026-11-09T16:01:00+01:00');
	}

	public function test_scanning_a_qr_makes_both_players_friends_at_once(): void
	{
		$token = $this->qrOf($this->lea);

		$this->scan($this->yanis, $token)
			->assertOk()
			->assertJsonCount(1, 'friends')
			->assertJsonPath('friends.0.player.pseudo', 'Lea')
			->assertJsonCount(0, 'received')
			->assertJsonCount(0, 'sent');

		Sanctum::actingAs($this->lea);
		$this->getJson('/api/me/friends')->assertJsonPath('friends.0.player.pseudo', 'Yanis');

		$friendship = Friendship::sole();
		$this->assertSame(FriendshipStatus::Accepted, $friendship->status);
		$this->assertNotNull($friendship->accepted_at);
	}

	public function test_a_pending_invitation_in_either_direction_is_accepted(): void
	{
		$tom = User::factory()->create(['pseudo' => 'Tom']);
		Friendship::create(['user_id' => $this->yanis->id, 'friend_id' => $this->lea->id, 'status' => FriendshipStatus::Pending]);
		Friendship::create(['user_id' => $this->lea->id, 'friend_id' => $tom->id, 'status' => FriendshipStatus::Pending]);

		$token = $this->qrOf($this->lea);
		$this->scan($this->yanis, $token)->assertOk();
		$this->scan($tom, $token)->assertOk();

		$this->assertSame(2, Friendship::count());
		$this->assertSame(2, Friendship::accepted()->count());
	}

	public function test_several_players_can_scan_the_same_qr(): void
	{
		$tom = User::factory()->create(['pseudo' => 'Tom']);
		$token = $this->qrOf($this->lea);

		$this->scan($this->yanis, $token)->assertOk();
		$this->scan($tom, $token)->assertOk();

		Sanctum::actingAs($this->lea);
		$this->getJson('/api/me/friends')->assertJsonCount(2, 'friends');
	}

	public function test_an_expired_or_invalid_qr_is_refused(): void
	{
		$token = $this->qrOf($this->lea);
		$this->travel(61)->seconds();

		$this->scan($this->yanis, $token)->assertUnprocessable()->assertJsonValidationErrors(['token' => self::INVALID]);
		$this->scan($this->yanis, 'pas-un-jeton')->assertUnprocessable()->assertJsonValidationErrors(['token' => self::INVALID]);
		$this->scan($this->yanis, str_repeat('a', 1001))->assertUnprocessable()->assertJsonValidationErrors(['token' => self::INVALID]);

		Sanctum::actingAs($this->yanis);
		$this->postJson('/api/me/friends/qr', [])->assertUnprocessable()->assertJsonValidationErrors('token');

		$this->assertSame(0, Friendship::count());
	}

	public function test_a_player_cannot_scan_their_own_qr(): void
	{
		$token = $this->qrOf($this->lea);

		$this->scan($this->lea, $token)
			->assertUnprocessable()
			->assertJsonValidationErrors(['token' => "C'est ton propre QR code : fais-le scanner par ton ami."]);

		$this->assertSame(0, Friendship::count());
	}

	public function test_a_block_in_either_direction_looks_like_an_invalid_qr(): void
	{
		$tom = User::factory()->create(['pseudo' => 'Tom']);
		Block::create(['user_id' => $this->lea->id, 'blocked_id' => $this->yanis->id]);
		Block::create(['user_id' => $tom->id, 'blocked_id' => $this->lea->id]);
		$token = $this->qrOf($this->lea);

		$this->scan($this->yanis, $token)->assertUnprocessable()->assertJsonValidationErrors(['token' => self::INVALID]);
		$this->scan($tom, $token)->assertUnprocessable()->assertJsonValidationErrors(['token' => self::INVALID]);

		$this->assertSame(0, Friendship::count());
	}

	public function test_scanning_the_qr_of_a_friend_is_refused(): void
	{
		$this->scan($this->yanis, $this->qrOf($this->lea))->assertOk();

		$this->scan($this->lea, $this->qrOf($this->yanis))
			->assertUnprocessable()
			->assertJsonValidationErrors(['token' => 'Vous êtes déjà amis.']);

		$this->assertSame(1, Friendship::count());
	}

	public function test_a_player_without_profile_can_neither_show_nor_scan_a_qr(): void
	{
		$withoutProfile = User::factory()->withoutProfile()->create();
		$token = $this->qrOf($this->lea);

		Sanctum::actingAs($withoutProfile);
		$this->getJson('/api/me/friends/qr')->assertStatus(409);
		$this->postJson('/api/me/friends/qr', ['token' => $token])->assertStatus(409);

		$this->assertSame(0, Friendship::count());
	}

	public function test_a_player_can_scan_twenty_qr_per_minute(): void
	{
		Sanctum::actingAs($this->yanis);

		for ($i = 0; $i < 20; $i++) {
			$this->postJson('/api/me/friends/qr', ['token' => 'pas-un-jeton'])->assertUnprocessable();
		}

		$this->postJson('/api/me/friends/qr', ['token' => 'pas-un-jeton'])->assertTooManyRequests();
	}
}
