<?php

namespace Tests\Feature\Quests;

use App\Enums\Ambition;
use App\Enums\QuestRepeat;
use App\Enums\QuestSource;
use App\Enums\QuestStatus;
use App\Models\Accessory;
use App\Models\Activity;
use App\Models\Companion;
use App\Models\Encounter;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\Quest;
use App\Models\QuestBan;
use App\Models\QuestTask;
use App\Models\TaskType;
use App\Models\User;
use App\Models\UserPosition;
use App\Models\UserQuest;
use Database\Seeders\QuestSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuestEngineTest extends TestCase
{
	use RefreshDatabase;

	private User $lea;

	protected function setUp(): void
	{
		parent::setUp();

		$this->travelTo('2026-10-14 15:00:00');
		$this->lea = User::factory()->create(['pseudo' => 'Lea', 'ambition' => Ambition::Fitness, 'created_at' => '2026-09-01 10:00:00']);
		Companion::factory()->for($this->lea)->create();
		Sanctum::actingAs($this->lea);
	}

	private function quest(array $attributes, array $tasks, array $rewards = []): Quest
	{
		$quest = Quest::factory()->create(['auto_assign' => true, ...$attributes]);
		$created = [];

		foreach ($tasks as $index => $task) {
			[$step, $type, $target, $extra] = array_pad($task, 4, []);
			$created[] = QuestTask::factory()->for($quest)->create([
				'step' => $step,
				'position' => $index + 1,
				'task_type_id' => TaskType::byCode($type)->id,
				'description' => "Tâche {$step}.".($index + 1),
				'target' => $target,
				...$extra,
			]);
		}

		foreach ($rewards as $reward) {
			$quest->rewards()->create([
				'step' => $reward['step'] ?? null,
				'quest_task_id' => isset($reward['task']) ? $created[$reward['task']]->id : null,
				'xp' => $reward['xp'] ?? 0,
				'accessory_id' => isset($reward['accessory']) ? Accessory::where('code', $reward['accessory'])->value('id') : null,
			]);
		}

		return $quest->fresh();
	}

	private function claim(int $userQuestId)
	{
		return $this->postJson("/api/me/quests/{$userQuestId}/claim");
	}

	private function wear(string $accessory)
	{
		return $this->putJson('/api/me/companion', ['name' => 'Bulle', 'species' => 'scuba', 'color' => 'ocean', 'accessories' => [$accessory]]);
	}

	private function walked(string $day, int $steps): void
	{
		Activity::factory()->for($this->lea)->create(['day' => $day, 'steps' => $steps, 'goal' => 5000]);
	}

	private function myQuests()
	{
		return $this->getJson('/api/me/quests')->assertOk();
	}

	private function act(int $userQuestId, int $taskId, array $body = [])
	{
		return $this->postJson("/api/me/quests/{$userQuestId}/tasks/{$taskId}", $body);
	}

	private function castle(): Place
	{
		return Place::factory()->ofType('point_of_interest')->create(['name' => 'Colline du Château', 'latitude' => 43.6953, 'longitude' => 7.2806, 'radius_m' => 100]);
	}

	public function test_it_requires_a_token(): void
	{
		$this->app['auth']->forgetGuards();

		$this->withHeader('Authorization', 'Bearer faux')->getJson('/api/me/quests')->assertUnauthorized();
	}

	public function test_quests_are_given_by_ambition_only_when_published_and_running(): void
	{
		$mine = $this->quest(['name' => 'Pour les sportifs', 'target_ambition' => Ambition::Fitness], [[1, 'steps', 1000]]);
		$everyone = $this->quest(['name' => 'Pour tous'], [[1, 'steps', 1000]]);
		$this->quest(['name' => 'Pour les explorateurs', 'target_ambition' => Ambition::Explore], [[1, 'steps', 1000]]);
		$this->quest(['name' => 'Donnée à la main', 'auto_assign' => false], [[1, 'steps', 1000]]);
		$this->quest(['name' => 'Brouillon', 'status' => QuestStatus::Draft], [[1, 'steps', 1000]]);
		$this->quest(['name' => 'Finie', 'ends_at' => '2026-10-10 00:00:00'], [[1, 'steps', 1000]]);
		$this->quest(['name' => 'Sans tâche'], []);

		$this->myQuests()
			->assertJsonCount(2, 'quests')
			->assertJsonPath('quests.0.quest_id', $mine->id)
			->assertJsonPath('quests.1.quest_id', $everyone->id)
			->assertJsonPath('quests.0.period', 'once')
			->assertJsonPath('quests.0.step', 1)
			->assertJsonPath('quests.0.steps', 1);

		$this->myQuests()->assertJsonCount(2, 'quests');
		$this->assertSame(2, UserQuest::count());
		$this->assertSame(QuestSource::Rule, UserQuest::first()->source);
	}

	public function test_a_zone_quest_needs_the_last_known_position_inside_the_zone(): void
	{
		$zone = Place::factory()->ofType('zone')->create(['latitude' => 43.70, 'longitude' => 7.27]);
		$zone->setOutline([[43.71, 7.26], [43.71, 7.28], [43.69, 7.28], [43.69, 7.26]]);
		$this->quest(['name' => 'Sponsor du centre', 'target_zone_id' => $zone->id], [[1, 'steps', 1000]]);

		$this->myQuests()->assertJsonCount(0, 'quests');

		$position = UserPosition::factory()->for($this->lea)->create(['latitude' => 43.60, 'longitude' => 7.10]);
		$this->myQuests()->assertJsonCount(0, 'quests');

		$position->update(['latitude' => 43.70, 'longitude' => 7.27]);
		$this->myQuests()->assertJsonCount(1, 'quests')->assertJsonPath('quests.0.name', 'Sponsor du centre');
	}

	public function test_a_repeated_quest_comes_back_each_period_and_expires_at_its_end(): void
	{
		$this->quest(['name' => 'Balade du jour', 'repeat' => QuestRepeat::Daily], [[1, 'steps', 5000]]);
		$this->quest(['name' => 'Défi de la semaine', 'repeat' => QuestRepeat::Weekly, 'ends_at' => '2026-10-16 12:00:00'], [[1, 'steps', 35000]]);

		$this->myQuests()
			->assertJsonPath('quests.0.period', '2026-10-14')
			->assertJsonPath('quests.0.expires_at', '2026-10-15T00:00:00+02:00')
			->assertJsonPath('quests.1.period', '2026-W42')
			->assertJsonPath('quests.1.expires_at', '2026-10-16T12:00:00+02:00');

		$this->travelTo('2026-10-15 08:00:00');

		$this->myQuests()->assertJsonCount(2, 'quests')->assertJsonPath('quests.1.period', '2026-10-15');
		$this->assertSame(3, UserQuest::count());

		$this->travelTo('2026-10-16 13:00:00');
		$this->myQuests()->assertJsonCount(1, 'quests')->assertJsonPath('quests.0.period', '2026-10-16');
	}

	public function test_only_the_tasks_of_the_current_step_are_sent(): void
	{
		$this->quest(['name' => 'En deux temps'], [[1, 'steps', 1000], [1, 'visit_place_type', 1, ['place_type_id' => PlaceType::where('code', 'park')->value('id')]], [2, 'scan_code', 1, ['description' => 'Secret de l\'étape 2', 'params' => ['code' => 'CACHE']]]]);

		$response = $this->myQuests()
			->assertJsonPath('quests.0.step', 1)
			->assertJsonPath('quests.0.steps', 2)
			->assertJsonCount(2, 'quests.0.tasks')
			->assertJsonPath('quests.0.tasks.0.action', 'auto')
			->assertJsonPath('quests.0.tasks.1.action', 'checkin')
			->assertJsonPath('quests.0.tasks.1.place_type', ['code' => 'park', 'label' => 'Parc']);

		$this->assertStringNotContainsString('Secret', $response->getContent());
		$this->assertStringNotContainsString('CACHE', $response->getContent());
	}

	public function test_steps_count_from_the_moment_their_step_is_unlocked(): void
	{
		$this->walked('2026-10-13', 8000);
		$this->quest(['name' => 'Marcheur'], [[1, 'steps', 1000], [2, 'steps', 2000]]);

		$this->myQuests()->assertJsonPath('quests.0.tasks.0.value', 0);

		$this->walked('2026-10-14', 1200);
		$this->myQuests()->assertJsonPath('quests.0.step', 2)->assertJsonPath('quests.0.tasks.0.value', 0)->assertJsonPath('quests.0.tasks.0.target', 2000);

		Activity::whereDate('day', '2026-10-14')->update(['steps' => 2900]);
		$this->myQuests()->assertJsonPath('quests.0.tasks.0.value', 1700);
	}

	public function test_rewards_are_earned_by_tasks_steps_and_the_quest_then_claimed_with_a_button(): void
	{
		Accessory::create(['code' => 'leaf_hat', 'label' => 'Chapeau de feuilles', 'category' => 'hat', 'image' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"/>', 'earnable' => true]);
		$castle = $this->castle();
		$quest = $this->quest(['name' => 'Forêt enchantée'], [
			[1, 'scan_code', 1, ['params' => ['code' => 'RENCONTRE']]],
			[1, 'visit_place', 1, ['place_id' => $castle->id]],
			[2, 'scan_code', 1, ['params' => ['code' => 'CLAIRIERE']]],
		], [
			['task' => 1, 'accessory' => 'leaf_hat'],
			['step' => 2, 'xp' => 120],
			['xp' => 300],
		]);
		[$meetTask, $castleTask, $clearingTask] = $quest->tasks;

		$userQuestId = $this->myQuests()
			->assertJsonPath('quests.0.status', 'active')
			->assertJsonPath('quests.0.tasks.0.rewards', [])
			->assertJsonPath('quests.0.tasks.1.rewards', [['xp' => 0, 'accessory' => 'leaf_hat']])
			->assertJsonPath('quests.0.step_rewards', [])
			->assertJsonPath('quests.0.quest_rewards', [['xp' => 300, 'accessory' => null]])
			->assertJsonPath('quests.0.rewards_to_claim', [])
			->json('quests.0.id');

		$this->act($userQuestId, $castleTask->id, ['latitude' => 43.6953, 'longitude' => 7.2806])
			->assertOk()
			->assertJsonPath('quest.rewards_to_claim.0.accessory', 'leaf_hat');
		$this->wear('leaf_hat')->assertUnprocessable();

		$this->claim($userQuestId)
			->assertOk()
			->assertJsonPath('claimed.0.accessory', 'leaf_hat')
			->assertJsonPath('quest.rewards_to_claim', [])
			->assertJsonPath('companion.unlocked_accessories', ['leaf_hat']);
		$this->wear('leaf_hat')->assertOk();

		$this->act($userQuestId, $meetTask->id, ['code' => 'RENCONTRE'])
			->assertOk()
			->assertJsonPath('quest.step', 2)
			->assertJsonPath('quest.step_rewards', [['xp' => 120, 'accessory' => null]]);

		$this->act($userQuestId, $clearingTask->id, ['code' => 'CLAIRIERE'])
			->assertOk()
			->assertJsonPath('status', 'completed')
			->assertJsonPath('quest.tasks', [])
			->assertJsonCount(2, 'quest.rewards_to_claim');

		$this->myQuests()->assertJsonCount(1, 'quests')->assertJsonPath('quests.0.status', 'completed');

		$this->claim($userQuestId)
			->assertOk()
			->assertJsonCount(2, 'claimed')
			->assertJsonPath('quest', null)
			->assertJsonPath('companion.xp', 120 + 300);

		$this->myQuests()->assertJsonCount(0, 'quests');
		$this->claim($userQuestId)->assertStatus(409);

		Sanctum::actingAs(User::factory()->create());
		$this->claim($userQuestId)->assertNotFound();
	}

	public function test_rewards_already_earned_can_still_be_claimed_after_a_failed_quest(): void
	{
		$questions = [['question' => 'Quelle mer ?', 'choices' => ['Manche', 'Méditerranée'], 'answer' => 1]];
		$quest = $this->quest(['name' => 'Quiz du port'], [[1, 'scan_code', 1, ['params' => ['code' => 'PORT']]], [2, 'quiz', 1, ['params' => ['questions' => $questions]]]], [['task' => 0, 'xp' => 40]]);
		$userQuestId = $this->myQuests()->json('quests.0.id');

		$this->act($userQuestId, $quest->tasks[0]->id, ['code' => 'PORT'])->assertOk();
		$this->act($userQuestId, $quest->tasks[1]->id, ['question' => 0, 'choice' => 0])->assertOk()->assertJsonPath('status', 'failed');

		$this->myQuests()->assertJsonPath('quests.0.status', 'failed')->assertJsonPath('quests.0.rewards_to_claim.0.xp', 40);
		$this->claim($userQuestId)->assertOk()->assertJsonPath('quest', null);
		$this->myQuests()->assertJsonCount(0, 'quests');
	}

	public function test_earnable_accessories_are_locked_until_won(): void
	{
		$accessories = collect($this->getJson('/api/companion/catalog')->json('accessories'))->keyBy('id');

		$this->assertTrue($accessories['crown']['earnable']);
		$this->assertFalse($accessories['tophat']['earnable']);

		$this->putJson('/api/me/companion', ['name' => 'Bulle', 'species' => 'scuba', 'color' => 'ocean', 'accessories' => ['medal']])
			->assertUnprocessable()
			->assertJsonValidationErrors(['accessories' => 'Cet accessoire se gagne en terminant une quête.']);
		$this->putJson('/api/me/companion', ['name' => 'Bulle', 'species' => 'scuba', 'color' => 'ocean', 'accessories' => ['tophat']])->assertOk();
	}

	public function test_a_check_in_validates_a_place_or_any_place_of_a_type(): void
	{
		$castle = $this->castle();
		Place::factory()->create(['name' => 'Jardin Albert Ier', 'latitude' => 43.6956, 'longitude' => 7.2686]);
		$quest = $this->quest(['name' => 'Tournée'], [[1, 'visit_place', 1, ['place_id' => $castle->id]], [1, 'visit_place_type', 1, ['place_type_id' => PlaceType::where('code', 'park')->value('id')]]]);
		$userQuest = $this->myQuests()->json('quests.0');
		[$castleTask, $parkTask] = $quest->tasks;

		$this->act($userQuest['id'], $castleTask->id, ['latitude' => 43.6956, 'longitude' => 7.2686])
			->assertUnprocessable()
			->assertJsonValidationErrors(['position' => "Tu n'es pas encore sur place."]);

		$this->act($userQuest['id'], $castleTask->id, ['latitude' => 43.6957, 'longitude' => 7.2810])
			->assertOk()
			->assertJsonPath('status', 'active')
			->assertJsonPath('quest.tasks.0.completed', true);

		$this->act($userQuest['id'], $parkTask->id, ['latitude' => 43.6956, 'longitude' => 7.2690])
			->assertOk()
			->assertJsonPath('status', 'completed')
			->assertJsonPath('quest', null);
	}

	public function test_a_place_type_task_needs_different_places(): void
	{
		$parkType = PlaceType::where('code', 'park')->value('id');
		Place::factory()->create(['name' => 'Jardin Albert Ier', 'latitude' => 43.6956, 'longitude' => 7.2686]);
		Place::factory()->create(['name' => 'Parc du Château', 'latitude' => 43.6953, 'longitude' => 7.2806]);
		$quest = $this->quest(['name' => 'Tour des parcs'], [[1, 'visit_place_type', 2, ['place_type_id' => $parkType]]]);
		$userQuestId = $this->myQuests()->json('quests.0.id');
		$albert = ['latitude' => 43.6956, 'longitude' => 7.2686];

		$this->act($userQuestId, $quest->tasks[0]->id, $albert)->assertOk()->assertJsonPath('quest.tasks.0.value', 1);
		$this->travel(1)->minutes();
		$this->act($userQuestId, $quest->tasks[0]->id, $albert)
			->assertUnprocessable()
			->assertJsonValidationErrors(['position' => 'Tu as déjà validé ce lieu : trouves-en un autre.']);
		$this->travel(1)->minutes();

		$this->act($userQuestId, $quest->tasks[0]->id, ['latitude' => 43.6953, 'longitude' => 7.2806])->assertOk()->assertJsonPath('status', 'completed');
	}

	public function test_a_precise_place_is_reached_in_one_visit_whatever_its_target(): void
	{
		$castle = $this->castle();
		$quest = $this->quest(['name' => 'Belvédère'], [[1, 'visit_place', 2, ['place_id' => $castle->id]]]);
		$userQuestId = $this->myQuests()->json('quests.0.id');

		$this->act($userQuestId, $quest->tasks[0]->id, ['latitude' => 43.6953, 'longitude' => 7.2806])->assertOk()->assertJsonPath('status', 'completed');
	}

	public function test_being_at_the_place_validates_the_task_without_the_button(): void
	{
		$castle = $this->castle();
		$this->quest(['name' => 'Belvédère'], [[1, 'visit_place', 1, ['place_id' => $castle->id]], [2, 'scan_code', 1, ['params' => ['code' => 'X']]]], [['step' => 1, 'xp' => 30]]);
		$this->myQuests();

		$this->postJson('/api/me/position', ['latitude' => 43.70, 'longitude' => 7.25])->assertNoContent();
		$this->myQuests()->assertJsonPath('quests.0.step', 1);
		$this->travel(1)->minutes();

		$this->postJson('/api/me/position', ['latitude' => 43.6954, 'longitude' => 7.2807, 'accuracy_m' => 12])->assertNoContent();

		$this->myQuests()
			->assertJsonPath('quests.0.step', 2)
			->assertJsonPath('quests.0.rewards_to_claim.0.xp', 30);
	}

	public function test_walking_past_several_places_of_a_type_counts_each_of_them(): void
	{
		$parkType = PlaceType::where('code', 'park')->value('id');
		Place::factory()->create(['name' => 'Jardin Albert Ier', 'latitude' => 43.6956, 'longitude' => 7.2686]);
		Place::factory()->create(['name' => 'Parc du Château', 'latitude' => 43.6953, 'longitude' => 7.2806]);
		$this->quest(['name' => 'Tour des parcs'], [[1, 'visit_place_type', 2, ['place_type_id' => $parkType]]]);
		$this->myQuests();

		$this->postJson('/api/me/position', ['latitude' => 43.6956, 'longitude' => 7.2686])->assertNoContent();
		$this->travel(1)->minutes();
		$this->postJson('/api/me/position', ['latitude' => 43.6956, 'longitude' => 7.2687])->assertNoContent();
		$this->myQuests()->assertJsonPath('quests.0.tasks.0.value', 1);
		$this->travel(10)->minutes();

		$this->postJson('/api/me/position', ['latitude' => 43.6953, 'longitude' => 7.2806])->assertNoContent();

		$this->assertNotNull(UserQuest::sole()->completed_at);
	}

	public function test_a_teleported_or_mocked_position_never_validates_a_place(): void
	{
		$castle = $this->castle();
		$this->quest(['name' => 'Belvédère'], [[1, 'visit_place', 1, ['place_id' => $castle->id]]]);
		$this->myQuests();
		$this->postJson('/api/me/position', ['latitude' => 48.8566, 'longitude' => 2.3522])->assertNoContent();
		$this->travel(1)->minutes();

		$this->postJson('/api/me/position', ['latitude' => 43.6953, 'longitude' => 7.2806])->assertNoContent();
		$this->postJson('/api/me/position', ['latitude' => 43.6953, 'longitude' => 7.2806, 'mocked' => true])->assertNoContent();

		$this->assertNull(UserQuest::sole()->completed_at);
		$this->assertSame(0, UserQuest::sole()->tasks()->whereNotNull('completed_at')->count());
	}

	public function test_an_old_position_never_validates_a_place(): void
	{
		$castle = $this->castle();
		$this->quest(['name' => 'Belvédère'], [[1, 'visit_place', 1, ['place_id' => $castle->id]]]);
		$this->myQuests();

		$this->postJson('/api/me/position', ['latitude' => 43.6953, 'longitude' => 7.2806, 'recorded_at' => '2026-10-13T14:00:00+02:00'])->assertNoContent();

		$this->assertNull(UserQuest::sole()->completed_at);
	}

	public function test_the_gps_accuracy_helps_a_check_in_only_up_to_a_limit(): void
	{
		$castle = $this->castle();
		$quest = $this->quest(['name' => 'Belvédère'], [[1, 'visit_place', 1, ['place_id' => $castle->id]]]);
		$userQuestId = $this->myQuests()->json('quests.0.id');
		$justOutside = ['latitude' => 43.6966, 'longitude' => 7.2806];

		$this->act($userQuestId, $quest->tasks[0]->id, [...$justOutside, 'accuracy_m' => 5])->assertUnprocessable();
		$this->act($userQuestId, $quest->tasks[0]->id, ['latitude' => 43.6990, 'longitude' => 7.2806, 'accuracy_m' => 1000000])->assertUnprocessable();
		$this->act($userQuestId, $quest->tasks[0]->id, [...$justOutside, 'accuracy_m' => 50])->assertOk()->assertJsonPath('status', 'completed');
	}

	public function test_a_check_in_from_a_teleported_position_bans_the_player_silently(): void
	{
		$castle = $this->castle();
		$quest = $this->quest(['name' => 'Belvédère'], [[1, 'visit_place', 1, ['place_id' => $castle->id]]]);
		$userQuestId = $this->myQuests()->json('quests.0.id');
		$this->postJson('/api/me/position', ['latitude' => 48.8566, 'longitude' => 2.3522])->assertNoContent();
		$this->travel(1)->minutes();

		$this->act($userQuestId, $quest->tasks[0]->id, ['latitude' => 43.6953, 'longitude' => 7.2806])
			->assertUnprocessable()
			->assertJsonValidationErrors(['position' => "Tu n'es pas encore sur place."]);

		$this->assertSame('teleport', QuestBan::sole()->reason);
	}

	public function test_an_offline_check_in_is_still_checked_against_a_newer_position(): void
	{
		$castle = $this->castle();
		$quest = $this->quest(['name' => 'Belvédère'], [[1, 'visit_place', 1, ['place_id' => $castle->id]]]);
		$userQuestId = $this->myQuests()->json('quests.0.id');
		$this->postJson('/api/me/position', ['latitude' => 48.8566, 'longitude' => 2.3522])->assertNoContent();

		$this->act($userQuestId, $quest->tasks[0]->id, ['latitude' => 43.6953, 'longitude' => 7.2806, 'recorded_at' => '2026-10-14T14:50:00+02:00'])
			->assertUnprocessable()
			->assertJsonValidationErrors(['position' => "Tu n'es pas encore sur place."]);

		$this->assertSame('teleport', QuestBan::sole()->reason);
		$this->assertSame(48.8566, UserPosition::sole()->latitude);
	}

	public function test_sending_steps_ignores_the_quests_with_no_automatic_task(): void
	{
		$this->quest(['name' => 'Marche'], [[1, 'steps', 100000]]);
		$this->myQuests();
		$send = fn () => $this->postJson('/api/me/steps', ['device_id' => 'phone-aaaa-1111', 'days' => [['day' => '2026-10-14', 'steps' => 100]]])->assertOk();
		$queries = function () use ($send): int {
			DB::flushQueryLog();
			DB::enableQueryLog();
			$send();
			DB::disableQueryLog();

			return count(DB::getQueryLog());
		};
		$send();
		$this->travel(1)->minutes();
		$before = $queries();

		foreach (range(1, 5) as $index) {
			$this->quest(['name' => "Code {$index}"], [[1, 'scan_code', 1, ['params' => ['code' => "C{$index}"]]]]);
		}

		$this->myQuests()->assertJsonCount(6, 'quests');
		$this->travel(1)->minutes();

		$this->assertSame($before, $queries());
	}

	public function test_a_check_in_from_a_mocked_position_bans_the_player_silently(): void
	{
		$castle = $this->castle();
		$quest = $this->quest(['name' => 'Belvédère'], [[1, 'visit_place', 1, ['place_id' => $castle->id]]]);
		$userQuestId = $this->myQuests()->json('quests.0.id');

		$this->act($userQuestId, $quest->tasks[0]->id, ['latitude' => 43.6953, 'longitude' => 7.2806, 'mocked' => true])
			->assertUnprocessable()
			->assertJsonValidationErrors(['position' => "Tu n'es pas encore sur place."]);

		$this->assertSame('mocked', QuestBan::sole()->reason);
	}

	public function test_steps_sent_by_the_phone_finish_a_quest_without_opening_the_quests(): void
	{
		$this->quest(['name' => 'Couronnement'], [[1, 'steps', 1000]], [['xp' => 800]]);
		$userQuestId = $this->myQuests()->json('quests.0.id');

		$this->postJson('/api/me/steps', ['device_id' => 'phone-aaaa-1111', 'days' => [['day' => '2026-10-14', 'steps' => 1000]]])
			->assertOk()
			->assertJsonPath('companion.xp', 100);

		$this->assertNotNull(UserQuest::sole()->completed_at);
		$this->claim($userQuestId)->assertOk()->assertJsonPath('companion.xp', 100 + 800);
	}

	public function test_a_banned_player_silently_fails_location_tasks(): void
	{
		$castle = $this->castle();
		$quest = $this->quest(['name' => 'Belvédère'], [[1, 'visit_place', 1, ['place_id' => $castle->id]]]);
		$userQuestId = $this->myQuests()->json('quests.0.id');
		QuestBan::factory()->for($this->lea)->create(['starts_at' => now(), 'ends_at' => now()->addDay()]);

		$this->act($userQuestId, $quest->tasks[0]->id, ['latitude' => 43.6953, 'longitude' => 7.2806])
			->assertUnprocessable()
			->assertJsonValidationErrors(['position' => "Tu n'es pas encore sur place."]);

		$this->travelTo('2026-10-15 15:00:01');

		$this->act($userQuestId, $quest->tasks[0]->id, ['latitude' => 43.6953, 'longitude' => 7.2806])->assertOk()->assertJsonPath('status', 'completed');
	}

	public function test_a_validation_made_offline_is_accepted_within_a_day(): void
	{
		$castle = $this->castle();
		$quest = $this->quest(['name' => 'Belvédère', 'repeat' => QuestRepeat::Daily], [[1, 'visit_place', 1, ['place_id' => $castle->id]]]);
		$userQuestId = $this->myQuests()->json('quests.0.id');
		$here = ['latitude' => 43.6953, 'longitude' => 7.2806];

		$this->travelTo('2026-10-15 00:30:00');

		$this->act($userQuestId, $quest->tasks[0]->id, [...$here, 'recorded_at' => '2026-10-13T12:00:00+02:00'])->assertUnprocessable()->assertJsonValidationErrors('recorded_at');
		$this->act($userQuestId, $quest->tasks[0]->id, [...$here, 'recorded_at' => '2026-10-14T23:40:00+02:00'])->assertOk()->assertJsonPath('status', 'completed');
	}

	public function test_a_code_is_checked_by_the_server_and_never_sent_to_the_app(): void
	{
		$quest = $this->quest(['name' => 'Pause café'], [[1, 'scan_code', 1, ['params' => ['code' => 'SCUBA-CAFE']]]]);
		$response = $this->myQuests();
		$this->assertStringNotContainsString('SCUBA-CAFE', $response->getContent());

		$this->act($response->json('quests.0.id'), $quest->tasks[0]->id, ['code' => 'MAUVAIS'])->assertUnprocessable()->assertJsonValidationErrors('code');
		$this->act($response->json('quests.0.id'), $quest->tasks[0]->id, ['code' => ' scuba-cafe '])->assertOk()->assertJsonPath('status', 'completed');
	}

	public function test_a_quiz_hides_its_answers_and_a_mistake_fails_the_quest(): void
	{
		$questions = [
			['question' => 'Quelle mer ?', 'choices' => ['Manche', 'Méditerranée'], 'answer' => 1],
			['question' => 'Quel monument ?', 'choices' => ['Château', 'Phare'], 'answer' => 0],
		];
		$quest = $this->quest(['name' => 'Quiz'], [[1, 'quiz', 2, ['params' => ['questions' => $questions]]]]);
		$response = $this->myQuests()
			->assertJsonPath('quests.0.tasks.0.target', 2)
			->assertJsonPath('quests.0.tasks.0.questions', [['question' => 'Quelle mer ?', 'choices' => ['Manche', 'Méditerranée']], ['question' => 'Quel monument ?', 'choices' => ['Château', 'Phare']]]);
		$this->assertArrayNotHasKey('answer', $response->json('quests.0.tasks.0.questions.0'));
		$userQuestId = $response->json('quests.0.id');
		$taskId = $quest->tasks[0]->id;

		$this->act($userQuestId, $taskId, ['question' => 1, 'choice' => 0])->assertUnprocessable()->assertJsonValidationErrors('question');
		$this->act($userQuestId, $taskId, ['question' => 0, 'choice' => 1])->assertOk()->assertJsonPath('quest.tasks.0.value', 1);
		$this->act($userQuestId, $taskId, ['question' => 1, 'choice' => 1])->assertOk()->assertJsonPath('status', 'failed')->assertJsonPath('quest', null);

		$this->myQuests()->assertJsonCount(0, 'quests');
		$this->act($userQuestId, $taskId, ['question' => 1, 'choice' => 0])->assertStatus(409);
	}

	public function test_meeting_counts_the_encounters_validated_after_the_step_was_unlocked(): void
	{
		$friend = User::factory()->create();
		Encounter::factory()->create(['user_id' => $this->lea->id, 'other_user_id' => $friend->id, 'validated_at' => now()->subDay()]);
		$this->quest(['name' => 'Rencontre'], [[1, 'meet_player', 1]]);

		$this->myQuests()->assertJsonPath('quests.0.tasks.0.value', 0);

		Encounter::factory()->create(['user_id' => $this->lea->id, 'other_user_id' => $friend->id, 'validated_at' => null]);
		$this->myQuests()->assertJsonPath('quests.0.tasks.0.value', 0);

		Encounter::query()->latest('id')->first()->update(['validated_at' => now()]);
		$this->myQuests()->assertJsonCount(0, 'quests');
		$this->assertNotNull(UserQuest::sole()->completed_at);
	}

	public function test_tasks_can_only_be_acted_on_by_their_player_in_the_current_step(): void
	{
		$quest = $this->quest(['name' => 'Mélange'], [[1, 'steps', 1000], [2, 'scan_code', 1, ['params' => ['code' => 'X']]]]);
		$userQuestId = $this->myQuests()->json('quests.0.id');
		[$stepsTask, $codeTask] = $quest->tasks;

		$this->act($userQuestId, $stepsTask->id)->assertStatus(409);
		$this->act($userQuestId, $codeTask->id, ['code' => 'X'])->assertNotFound();

		Sanctum::actingAs(User::factory()->create());
		$this->act($userQuestId, $stepsTask->id)->assertNotFound();
	}

	public function test_an_expired_quest_disappears(): void
	{
		$quest = $this->quest(['name' => 'Du jour', 'repeat' => QuestRepeat::Daily], [[1, 'scan_code', 1, ['params' => ['code' => 'X']]]]);
		$userQuestId = $this->myQuests()->json('quests.0.id');

		$this->travelTo('2026-10-15 08:00:00');
		$quest->update(['status' => QuestStatus::Archived]);

		$this->myQuests()->assertJsonCount(0, 'quests');
		$this->act($userQuestId, $quest->tasks[0]->id, ['code' => 'X'])->assertStatus(409);
	}

	public function test_an_admin_can_give_a_quest_to_a_player(): void
	{
		$quest = $this->quest(['name' => 'Cadeau', 'auto_assign' => false], [[1, 'steps', 1000]]);

		$this->artisan('quetes:donner', ['quete' => $quest->id, 'joueur' => $this->lea->id])->assertSuccessful();
		$this->artisan('quetes:donner', ['quete' => 999, 'joueur' => $this->lea->id])->assertFailed();

		$this->assertSame(QuestSource::Manual, UserQuest::sole()->source);
		$this->myQuests()->assertJsonPath('quests.0.name', 'Cadeau');
	}

	public function test_the_demo_quests_can_be_seeded_twice_without_duplicates(): void
	{
		$this->seed(QuestSeeder::class);
		$this->seed(QuestSeeder::class);

		$this->assertSame(5, Quest::count());
		$this->assertSame(10, QuestTask::count());
		$this->assertSame(['Premiers pas', 'Balade du jour', 'Défi de la semaine'], collect($this->myQuests()->json('quests'))->pluck('name')->all());
	}
}
