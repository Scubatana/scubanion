<?php

namespace Tests\Feature\Friends;

use App\Enums\FriendshipStatus;
use App\Models\Cheer;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheerStreakTest extends TestCase
{
	use RefreshDatabase;

	private User $lea;

	private User $yanis;

	private Friendship $friendship;

	protected function setUp(): void
	{
		parent::setUp();

		$this->travelTo('2026-10-16 15:00:00');
		$this->lea = User::factory()->create(['pseudo' => 'Lea', 'created_at' => '2026-09-01 09:00:00']);
		$this->yanis = User::factory()->create(['pseudo' => 'Yanis', 'created_at' => '2026-09-01 09:00:00']);
		$this->friendship = Friendship::create([
			'user_id' => $this->lea->id,
			'friend_id' => $this->yanis->id,
			'status' => FriendshipStatus::Accepted,
			'accepted_at' => '2026-10-01 10:00:00',
		]);
	}

	private function exchange(string ...$days): void
	{
		foreach ($days as $day) {
			Cheer::create(['user_id' => $this->lea->id, 'friend_id' => $this->yanis->id, 'day' => $day]);
			Cheer::create(['user_id' => $this->yanis->id, 'friend_id' => $this->lea->id, 'day' => $day]);
		}
	}

	private function streakOf(User $player): array
	{
		Sanctum::actingAs($player);

		return $this->getJson('/api/me/friends')->assertOk()->json('friends.0.cheer.streak');
	}

	public function test_there_is_no_streak_before_the_first_exchange(): void
	{
		$this->assertSame(['days' => 0, 'record' => 0], $this->streakOf($this->lea));
	}

	public function test_a_cheer_without_an_answer_does_not_count(): void
	{
		Cheer::create(['user_id' => $this->lea->id, 'friend_id' => $this->yanis->id, 'day' => '2026-10-15']);
		Cheer::create(['user_id' => $this->lea->id, 'friend_id' => $this->yanis->id, 'day' => '2026-10-16']);

		$this->assertSame(['days' => 0, 'record' => 0], $this->streakOf($this->lea));
	}

	public function test_days_of_exchange_in_a_row_count_for_both_friends(): void
	{
		$this->exchange('2026-10-14', '2026-10-15', '2026-10-16');

		$this->assertSame(['days' => 3, 'record' => 3], $this->streakOf($this->lea));
		$this->assertSame(['days' => 3, 'record' => 3], $this->streakOf($this->yanis));
	}

	public function test_today_is_not_missed_until_midnight(): void
	{
		$this->exchange('2026-10-14', '2026-10-15');
		Cheer::create(['user_id' => $this->lea->id, 'friend_id' => $this->yanis->id, 'day' => '2026-10-16']);

		$this->assertSame(['days' => 2, 'record' => 2], $this->streakOf($this->lea));
	}

	public function test_one_missed_day_a_week_does_not_break_the_streak(): void
	{
		$this->exchange('2026-10-13', '2026-10-14', '2026-10-16');

		$this->assertSame(['days' => 3, 'record' => 3], $this->streakOf($this->lea));
	}

	public function test_the_joker_comes_back_each_monday(): void
	{
		$this->exchange('2026-10-09', '2026-10-10', '2026-10-13', '2026-10-14', '2026-10-15', '2026-10-16');

		$this->assertSame(['days' => 6, 'record' => 6], $this->streakOf($this->lea));
	}

	public function test_a_second_missed_day_in_the_week_breaks_the_streak_but_keeps_the_record(): void
	{
		$this->exchange('2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08', '2026-10-14', '2026-10-15', '2026-10-16');

		$this->assertSame(['days' => 3, 'record' => 4], $this->streakOf($this->lea));
	}

	public function test_a_streak_ends_when_yesterday_was_a_second_missed_day(): void
	{
		$this->exchange('2026-10-12', '2026-10-13');

		$this->assertSame(['days' => 0, 'record' => 2], $this->streakOf($this->lea));
	}

	public function test_exchanges_from_before_the_friendship_do_not_count(): void
	{
		$this->exchange('2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09');
		$this->friendship->update(['accepted_at' => '2026-10-15 18:00:00']);
		$this->exchange('2026-10-15', '2026-10-16');

		$this->assertSame(['days' => 2, 'record' => 2], $this->streakOf($this->lea));
	}

	public function test_each_friend_has_their_own_streak(): void
	{
		$tom = User::factory()->create(['pseudo' => 'Tom', 'created_at' => '2026-09-01 09:00:00']);
		Friendship::create(['user_id' => $tom->id, 'friend_id' => $this->lea->id, 'status' => FriendshipStatus::Accepted, 'accepted_at' => '2026-10-01 10:00:00']);
		$this->exchange('2026-10-15', '2026-10-16');
		Cheer::create(['user_id' => $tom->id, 'friend_id' => $this->lea->id, 'day' => '2026-10-16']);
		Cheer::create(['user_id' => $this->lea->id, 'friend_id' => $tom->id, 'day' => '2026-10-16']);

		Sanctum::actingAs($this->lea);
		$this->getJson('/api/me/friends')
			->assertOk()
			->assertJsonPath('friends.0.cheer.streak', ['days' => 2, 'record' => 2])
			->assertJsonPath('friends.1.cheer.streak', ['days' => 1, 'record' => 1]);
	}
}
