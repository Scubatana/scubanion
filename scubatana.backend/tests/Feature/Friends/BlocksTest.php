<?php

namespace Tests\Feature\Friends;

use App\Models\Block;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlocksTest extends TestCase
{
	use RefreshDatabase;

	private User $lea;

	private User $yanis;

	protected function setUp(): void
	{
		parent::setUp();

		$this->lea = User::factory()->create(['pseudo' => 'Lea']);
		$this->yanis = User::factory()->create(['pseudo' => 'Yanis']);
	}

	private function invite(User $from, User $to)
	{
		Sanctum::actingAs($from);

		return $this->postJson('/api/me/friends', ['code' => $to->friend_code]);
	}

	private function block(User $from, User $to)
	{
		Sanctum::actingAs($from);

		return $this->postJson('/api/me/blocks', ['player_id' => $to->id]);
	}

	public function test_it_requires_a_token(): void
	{
		$this->getJson('/api/me/blocks')->assertUnauthorized();
		$this->postJson('/api/me/blocks', ['player_id' => $this->yanis->id])->assertUnauthorized();
	}

	public function test_a_player_can_block_another_player_and_see_them_in_their_list(): void
	{
		$this->block($this->lea, $this->yanis)
			->assertOk()
			->assertJsonPath('blocked.0.player', ['id' => $this->yanis->id, 'pseudo' => 'Yanis']);

		$this->getJson('/api/me/blocks')->assertOk()->assertJsonCount(1, 'blocked');
	}

	public function test_the_blocked_player_does_not_see_the_block(): void
	{
		$this->block($this->lea, $this->yanis);

		Sanctum::actingAs($this->yanis);
		$this->getJson('/api/me/blocks')->assertOk()->assertJsonCount(0, 'blocked');
	}

	public function test_blocking_twice_keeps_a_single_block(): void
	{
		$this->block($this->lea, $this->yanis)->assertOk();
		$this->block($this->lea, $this->yanis)->assertOk()->assertJsonCount(1, 'blocked');

		$this->assertSame(1, Block::count());
	}

	public function test_it_refuses_a_missing_or_unknown_player_and_oneself(): void
	{
		Sanctum::actingAs($this->lea);

		$this->postJson('/api/me/blocks', [])->assertUnprocessable()->assertJsonValidationErrors('player_id');
		$this->postJson('/api/me/blocks', ['player_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('player_id');
		$this->postJson('/api/me/blocks', ['player_id' => $this->lea->id])->assertUnprocessable()->assertJsonValidationErrors('player_id');

		$this->assertSame(0, Block::count());
	}

	public function test_blocking_removes_the_friendship_and_pending_invitations(): void
	{
		$tom = User::factory()->create(['pseudo' => 'Tom']);
		$this->invite($this->lea, $this->yanis);
		Sanctum::actingAs($this->yanis);
		$this->postJson('/api/me/friends', ['code' => $this->lea->friend_code])->assertOk();
		$this->invite($tom, $this->lea)->assertOk();

		$this->block($this->lea, $this->yanis)->assertOk();
		$this->block($this->lea, $tom)->assertOk();

		$this->assertSame(0, Friendship::count());
	}

	public function test_blocking_does_not_touch_other_friendships(): void
	{
		$tom = User::factory()->create(['pseudo' => 'Tom']);
		$this->invite($this->lea, $tom);

		$this->block($this->lea, $this->yanis)->assertOk();

		$this->assertSame(1, Friendship::count());
	}

	public function test_a_player_can_unblock(): void
	{
		$blockId = $this->block($this->lea, $this->yanis)->json('blocked.0.id');

		$this->deleteJson("/api/me/blocks/{$blockId}")->assertOk()->assertJsonCount(0, 'blocked');

		$this->invite($this->yanis, $this->lea)->assertOk();
	}

	public function test_only_the_player_who_blocked_can_unblock(): void
	{
		$blockId = $this->block($this->lea, $this->yanis)->json('blocked.0.id');

		Sanctum::actingAs($this->yanis);
		$this->deleteJson("/api/me/blocks/{$blockId}")->assertNotFound();

		$this->assertSame(1, Block::count());
	}

	public function test_a_blocked_player_cannot_invite_the_player_who_blocked_them(): void
	{
		Block::create(['user_id' => $this->lea->id, 'blocked_id' => $this->yanis->id]);

		$this->invite($this->yanis, $this->lea)
			->assertUnprocessable()
			->assertJsonValidationErrors(['code' => "Aucun joueur n'a ce code."]);

		$this->assertSame(0, Friendship::count());
	}

	public function test_a_player_cannot_invite_someone_they_blocked(): void
	{
		Block::create(['user_id' => $this->lea->id, 'blocked_id' => $this->yanis->id]);

		$this->invite($this->lea, $this->yanis)
			->assertUnprocessable()
			->assertJsonValidationErrors(['code' => "Aucun joueur n'a ce code."]);

		$this->assertSame(0, Friendship::count());
	}
}
