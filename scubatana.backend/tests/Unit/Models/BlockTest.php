<?php

namespace Tests\Unit\Models;

use App\Models\Block;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BlockTest extends TestCase
{
	use RefreshDatabase;

	public function test_a_block_knows_who_blocked_whom(): void
	{
		[$blocker, $blocked] = User::factory()->count(2)->create();

		$block = Block::create(['user_id' => $blocker->id, 'blocked_id' => $blocked->id])->fresh();

		$this->assertTrue($block->blocker->is($blocker));
		$this->assertTrue($block->blocked->is($blocked));
	}

	public function test_a_player_cannot_block_themselves(): void
	{
		$player = User::factory()->create();

		$this->expectException(InvalidArgumentException::class);

		Block::create(['user_id' => $player->id, 'blocked_id' => $player->id]);
	}

	public function test_a_player_cannot_block_the_same_player_twice(): void
	{
		[$blocker, $blocked] = User::factory()->count(2)->create();
		Block::create(['user_id' => $blocker->id, 'blocked_id' => $blocked->id]);

		$this->expectException(QueryException::class);

		Block::create(['user_id' => $blocker->id, 'blocked_id' => $blocked->id]);
	}

	public function test_between_finds_a_block_in_either_direction(): void
	{
		[$blocker, $blocked, $stranger] = User::factory()->count(3)->create();
		Block::create(['user_id' => $blocker->id, 'blocked_id' => $blocked->id]);

		$this->assertTrue(Block::between($blocker, $blocked)->exists());
		$this->assertTrue(Block::between($blocked, $blocker)->exists());
		$this->assertFalse(Block::between($blocker, $stranger)->exists());
	}

	public function test_blocks_are_deleted_with_the_account(): void
	{
		[$blocker, $blocked] = User::factory()->count(2)->create();
		Block::create(['user_id' => $blocker->id, 'blocked_id' => $blocked->id]);

		$blocked->delete();

		$this->assertSame(0, Block::count());
	}
}
