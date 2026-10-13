<?php

namespace Tests\Unit\Models;

use App\Models\Encounter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class EncounterTest extends TestCase
{
	use RefreshDatabase;

	public function test_a_pair_of_players_is_always_stored_in_the_same_order(): void
	{
		[$first, $second] = User::factory()->count(2)->create();

		$encounter = Encounter::factory()->create(['user_id' => $second->id, 'other_user_id' => $first->id])->fresh();

		$this->assertSame($first->id, $encounter->user_id);
		$this->assertSame($second->id, $encounter->other_user_id);
		$this->assertTrue($encounter->user->is($first));
		$this->assertTrue($encounter->otherUser->is($second));
	}

	public function test_a_player_cannot_meet_themselves(): void
	{
		$player = User::factory()->create();

		$this->expectException(InvalidArgumentException::class);

		Encounter::factory()->create(['user_id' => $player->id, 'other_user_id' => $player->id]);
	}

	public function test_involving_finds_the_encounters_of_a_player_on_either_side(): void
	{
		[$first, $middle, $last] = User::factory()->count(3)->create();
		$withFirst = Encounter::factory()->create(['user_id' => $first->id, 'other_user_id' => $middle->id]);
		$withLast = Encounter::factory()->create(['user_id' => $middle->id, 'other_user_id' => $last->id]);
		Encounter::factory()->create(['user_id' => $first->id, 'other_user_id' => $last->id]);

		$found = Encounter::involving($middle)->orderBy('id')->pluck('id')->all();

		$this->assertSame([$withFirst->id, $withLast->id], $found);
	}

	public function test_it_is_validated_once_it_has_a_validation_date(): void
	{
		$encounter = Encounter::factory()->create();

		$this->assertFalse($encounter->isValidated());

		$encounter->update(['validated_at' => now()]);

		$this->assertTrue($encounter->fresh()->isValidated());
	}

	public function test_an_encounter_goes_away_with_either_player(): void
	{
		[$first, $second, $third] = User::factory()->count(3)->create();
		Encounter::factory()->create(['user_id' => $first->id, 'other_user_id' => $second->id]);
		Encounter::factory()->create(['user_id' => $second->id, 'other_user_id' => $third->id]);
		Encounter::factory()->create(['user_id' => $first->id, 'other_user_id' => $third->id]);

		$second->delete();

		$this->assertSame(1, Encounter::count());
		$this->assertSame(0, Encounter::involving($second)->count());
	}
}
