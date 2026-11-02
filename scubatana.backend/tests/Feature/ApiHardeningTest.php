<?php

namespace Tests\Feature;

use App\Enums\QuestSource;
use App\Models\Accessory;
use App\Models\Companion;
use App\Models\Place;
use App\Models\Quest;
use App\Models\QuestBan;
use App\Models\QuestTask;
use App\Models\TaskType;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\QuestEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiHardeningTest extends TestCase
{
	use RefreshDatabase;

	public function test_rate_limiting_uses_forwarded_ip_from_trusted_proxy_and_ignores_untrusted_spoofing(): void
	{
		Mail::fake();

		foreach (range(1, 10) as $attempt) {
			$this->withServerVariables(['REMOTE_ADDR' => '172.18.0.2'])
				->withHeader('X-Forwarded-For', '198.51.100.10')
				->postJson('/api/auth/code', ['email' => "joueur{$attempt}@example.com"])
				->assertNoContent();
		}

		$this->withServerVariables(['REMOTE_ADDR' => '172.18.0.2'])
			->withHeader('X-Forwarded-For', '198.51.100.10')
			->postJson('/api/auth/code', ['email' => 'bloque@example.com'])
			->assertTooManyRequests();

		$this->withServerVariables(['REMOTE_ADDR' => '172.18.0.2'])
			->withHeader('X-Forwarded-For', '198.51.100.11')
			->postJson('/api/auth/code', ['email' => 'autre-ip@example.com'])
			->assertNoContent();

		$this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
			->withHeader('X-Forwarded-For', '203.0.113.99')
			->postJson('/api/auth/code', ['email' => 'usurpation@example.com'])
			->assertTooManyRequests();
	}

	public function test_default_validation_errors_are_returned_in_french(): void
	{
		Mail::fake();

		$response = $this->postJson('/api/auth/code', ['email' => 'pas-un-email'])
			->assertUnprocessable()
			->assertJsonValidationErrors('email');

		$this->assertSame('Indique une adresse e-mail valide.', $response->json('errors.email.0'));

		$user = User::factory()->create();
		Sanctum::actingAs($user);

		$goalResponse = $this->putJson('/api/me/goal', ['daily_step_goal' => 100])
			->assertUnprocessable()
			->assertJsonValidationErrors('daily_step_goal');

		$this->assertStringContainsString('objectif quotidien de pas', $goalResponse->json('errors.daily_step_goal.0'));
	}

	public function test_retired_worn_accessory_still_prevents_wearing_a_second_accessory_of_the_same_category(): void
	{
		$user = User::factory()->create();
		$companion = Companion::factory()->for($user)->create(['accessories' => ['crown']]);
		Accessory::where('code', 'crown')->update(['active' => false]);
		Sanctum::actingAs($user);

		$this->putJson('/api/me/companion', [
			'name' => $companion->name,
			'species' => $companion->species,
			'color' => $companion->color,
			'accessories' => ['crown', 'tophat'],
		])->assertUnprocessable()->assertJsonValidationErrors('accessories');
	}

	public function test_floating_point_gps_accuracy_is_accepted_and_rounded_for_positions_and_quest_checkins(): void
	{
		$user = User::factory()->create();
		Companion::factory()->for($user)->create();
		Sanctum::actingAs($user);

		$this->postJson('/api/me/position', [
			'latitude' => 43.6953,
			'longitude' => 7.2806,
			'accuracy_m' => 12.6,
		])->assertNoContent();

		$this->assertSame(13, UserPosition::sole()->accuracy_m);

		$place = Place::factory()->create(['latitude' => 43.6953, 'longitude' => 7.2806, 'radius_m' => 40]);
		$quest = Quest::factory()->create();
		$task = QuestTask::factory()->for($quest)->create([
			'step' => 1,
			'position' => 1,
			'task_type_id' => TaskType::byCode('visit_place')->id,
			'place_id' => $place->id,
			'target' => 1,
		]);
		$userQuest = app(QuestEngine::class)->give($user, $quest->fresh(), QuestSource::Manual, now());

		$this->postJson("/api/me/quests/{$userQuest->id}/tasks/{$task->id}", [
			'latitude' => 43.6953,
			'longitude' => 7.2806,
			'accuracy_m' => 18.4,
		])->assertOk()->assertJsonPath('status', 'completed');
	}

	public function test_expired_quest_bans_older_than_thirty_days_are_pruned(): void
	{
		$this->travelTo('2026-11-15 12:00:00');
		$user = User::factory()->create();

		QuestBan::create([
			'user_id' => $user->id,
			'reason' => 'teleport',
			'evidence' => ['distance_m' => 600000],
			'starts_at' => '2026-10-10 12:00:00',
			'ends_at' => '2026-10-11 12:00:00',
		]);
		$recentExpired = QuestBan::create([
			'user_id' => $user->id,
			'reason' => 'mocked',
			'evidence' => ['latitude' => 43.7],
			'starts_at' => '2026-11-01 12:00:00',
			'ends_at' => '2026-11-02 12:00:00',
		]);
		$active = QuestBan::create([
			'user_id' => $user->id,
			'reason' => 'mocked',
			'evidence' => ['latitude' => 43.7],
			'starts_at' => '2026-11-15 10:00:00',
			'ends_at' => '2026-11-16 10:00:00',
		]);

		$this->artisan('model:prune', ['--model' => [QuestBan::class]])->assertSuccessful();

		$this->assertEqualsCanonicalizing([$recentExpired->id, $active->id], QuestBan::pluck('id')->all());
	}
}
