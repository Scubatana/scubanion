<?php

namespace Tests\Feature\Friends;

use App\Models\Activity;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrivacyAndValidationTest extends TestCase
{
	use RefreshDatabase;

	public function test_privacy_defaults_to_hidden_and_can_be_updated_independently(): void
	{
		$this->travelTo('2026-10-13 15:00:00');
		$lea = User::factory()->create(['pseudo' => 'Lea']);
		$yanis = User::factory()->create(['pseudo' => 'Yanis']);
		Activity::factory()->for($yanis)->create(['day' => '2026-10-13', 'steps' => 6000]);
		Friendship::create([
			'user_id' => $lea->id,
			'friend_id' => $yanis->id,
			'status' => 'accepted',
			'accepted_at' => now(),
		]);

		Sanctum::actingAs($yanis);
		$this->getJson('/api/me')
			->assertOk()
			->assertJsonPath('player.privacy', ['share_steps' => false, 'share_streak' => false]);

		$this->putJson('/api/me/privacy', ['share_steps' => true, 'share_streak' => false])
			->assertOk()
			->assertJsonPath('player.privacy', ['share_steps' => true, 'share_streak' => false]);

		Sanctum::actingAs($lea);
		$this->getJson('/api/me/friends')
			->assertOk()
			->assertJsonPath('friends.0.player.today.steps', 6000)
			->assertJsonPath('friends.0.player.streak', null);

		Sanctum::actingAs($yanis);
		$this->putJson('/api/me/privacy', ['share_steps' => false, 'share_streak' => true])
			->assertOk()
			->assertJsonPath('player.privacy', ['share_steps' => false, 'share_streak' => true]);

		Sanctum::actingAs($lea);
		$this->getJson('/api/me/friends')
			->assertOk()
			->assertJsonPath('friends.0.player.today', null)
			->assertJsonPath('friends.0.player.streak.days', 1);
	}

	public function test_privacy_update_validates_boolean_fields_and_requires_auth(): void
	{
		$this->putJson('/api/me/privacy', ['share_steps' => true, 'share_streak' => false])->assertUnauthorized();

		Sanctum::actingAs(User::factory()->create());

		$this->putJson('/api/me/privacy', [])
			->assertUnprocessable()
			->assertJsonValidationErrors(['share_steps', 'share_streak']);

		$this->putJson('/api/me/privacy', ['share_steps' => 'oui', 'share_streak' => 'non'])
			->assertUnprocessable()
			->assertJsonValidationErrors(['share_steps', 'share_streak']);
	}

	public function test_players_cannot_block_or_report_accounts_without_completed_profile(): void
	{
		$lea = User::factory()->create(['pseudo' => 'Lea']);
		$incomplete = User::factory()->withoutProfile()->create();

		Sanctum::actingAs($lea);

		$this->postJson('/api/me/blocks', ['player_id' => $incomplete->id])
			->assertUnprocessable()
			->assertJsonValidationErrors('player_id');

		$this->postJson('/api/me/reports', ['player_id' => $incomplete->id, 'reason' => 'pseudo'])
			->assertUnprocessable()
			->assertJsonValidationErrors('player_id');
	}
}
