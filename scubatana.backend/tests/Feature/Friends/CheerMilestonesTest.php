<?php

namespace Tests\Feature\Friends;

use App\Enums\FriendshipStatus;
use App\Models\Accessory;
use App\Models\Cheer;
use App\Models\Companion;
use App\Models\Friendship;
use App\Models\User;
use App\Models\UserAccessory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheerMilestonesTest extends TestCase
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
			'accepted_at' => '2026-08-01 10:00:00',
		]);
	}

	private function exchangeBefore(int $days): void
	{
		for ($ago = 1; $ago <= $days; $ago++) {
			$day = now()->subDays($ago)->toDateString();
			Cheer::create(['user_id' => $this->lea->id, 'friend_id' => $this->yanis->id, 'day' => $day]);
			Cheer::create(['user_id' => $this->yanis->id, 'friend_id' => $this->lea->id, 'day' => $day]);
		}
	}

	private function cheer(User $from)
	{
		Sanctum::actingAs($from);

		return $this->postJson("/api/me/friends/{$this->friendship->id}/cheer")->assertOk();
	}

	private function owned(User $player): array
	{
		return $player->fresh()->ownedAccessoryCodes();
	}

	public function test_the_seventh_day_of_exchange_unlocks_the_scarf_for_both_friends(): void
	{
		$this->exchangeBefore(6);
		$this->cheer($this->yanis)->assertJsonPath('unlocked', []);

		$this->cheer($this->lea)->assertJsonPath('unlocked', ['paw_scarf']);

		$this->assertSame(['paw_scarf'], $this->owned($this->lea));
		$this->assertSame(['paw_scarf'], $this->owned($this->yanis));
	}

	public function test_a_cheer_without_an_answer_unlocks_nothing_yet(): void
	{
		$this->exchangeBefore(6);

		$this->cheer($this->lea)->assertJsonPath('unlocked', []);

		$this->assertSame([], $this->owned($this->lea));
		$this->assertSame([], $this->owned($this->yanis));
	}

	public function test_six_days_are_not_enough(): void
	{
		$this->exchangeBefore(5);
		$this->cheer($this->yanis);

		$this->cheer($this->lea)->assertJsonPath('unlocked', []);
	}

	public function test_an_accessory_is_never_given_twice(): void
	{
		$this->exchangeBefore(6);
		$this->cheer($this->yanis);
		$this->cheer($this->lea)->assertJsonPath('unlocked', ['paw_scarf']);

		$tom = User::factory()->create(['pseudo' => 'Tom', 'created_at' => '2026-09-01 09:00:00']);
		$withTom = Friendship::create(['user_id' => $tom->id, 'friend_id' => $this->lea->id, 'status' => FriendshipStatus::Accepted, 'accepted_at' => '2026-08-01 10:00:00']);

		for ($ago = 1; $ago <= 6; $ago++) {
			Cheer::create(['user_id' => $tom->id, 'friend_id' => $this->lea->id, 'day' => now()->subDays($ago)->toDateString()]);
			Cheer::create(['user_id' => $this->lea->id, 'friend_id' => $tom->id, 'day' => now()->subDays($ago)->toDateString()]);
		}

		Sanctum::actingAs($tom);
		$this->postJson("/api/me/friends/{$withTom->id}/cheer")->assertOk()->assertJsonPath('unlocked', []);
		Sanctum::actingAs($this->lea);
		$this->postJson("/api/me/friends/{$withTom->id}/cheer")->assertOk()->assertJsonPath('unlocked', []);

		$this->assertSame(['paw_scarf'], $this->owned($tom));
		$this->assertSame(1, UserAccessory::where('user_id', $this->lea->id)->count());
	}

	public function test_the_hundredth_day_unlocks_the_glasses(): void
	{
		$this->friendship->update(['accepted_at' => '2026-06-01 10:00:00']);
		$this->exchangeBefore(99);
		UserAccessory::insert(collect([$this->lea, $this->yanis])->crossJoin(['paw_scarf', 'paw_bucket_hat'])->map(fn ($pair) => [
			'user_id' => $pair[0]->id,
			'accessory_id' => Accessory::where('code', $pair[1])->value('id'),
			'unlocked_at' => now(),
		])->all());
		$this->cheer($this->yanis)->assertJsonPath('unlocked', []);

		$this->cheer($this->lea)->assertJsonPath('unlocked', ['starfish_glasses']);

		$this->assertEqualsCanonicalizing(['paw_scarf', 'paw_bucket_hat', 'starfish_glasses'], $this->owned($this->yanis));
	}

	public function test_a_streak_from_before_the_milestones_existed_is_rewarded_at_the_next_cheer(): void
	{
		$this->exchangeBefore(40);

		$this->cheer($this->lea)->assertJsonPath('unlocked', ['paw_scarf', 'paw_bucket_hat']);

		$this->assertEqualsCanonicalizing(['paw_scarf', 'paw_bucket_hat'], $this->owned($this->yanis));
	}

	public function test_an_unlocked_accessory_can_be_worn(): void
	{
		Companion::factory()->for($this->lea)->create(['species' => 'dog', 'color' => 'sand', 'accessories' => []]);
		Sanctum::actingAs($this->lea);
		$wear = fn () => $this->putJson('/api/me/companion', ['name' => 'Bulle', 'species' => 'dog', 'color' => 'sand', 'accessories' => ['paw_scarf']]);

		$wear()->assertStatus(422)->assertJsonValidationErrors(['accessories' => "Cet accessoire n'est pas encore débloqué."]);

		$this->exchangeBefore(6);
		$this->cheer($this->yanis);
		$this->cheer($this->lea);

		Sanctum::actingAs($this->lea);
		$wear()->assertOk();
		$this->assertSame(['paw_scarf'], $this->lea->fresh()->companion->accessories);
	}
}
