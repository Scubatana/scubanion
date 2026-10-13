<?php

namespace Tests\Feature\Quests;

use App\Enums\Ambition;
use App\Enums\QuestRepeat;
use App\Enums\QuestSource;
use App\Enums\QuestStatus;
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

	/**
	 * @param  array<int, array{0: int, 1: string, 2: int, 3?: array}>  $tasks  [étape, type, cible, attributs]
	 */
	private function quest(array $attributes, array $tasks): Quest
	{
		$quest = Quest::factory()->create(['auto_assign' => true, ...$attributes]);

		foreach ($tasks as $index => $task) {
			[$step, $type, $target, $extra] = array_pad($task, 4, []);
			QuestTask::factory()->for($quest)->create([
				'step' => $step,
				'position' => $index + 1,
				'task_type_id' => TaskType::byCode($type)->id,
				'description' => "Tâche {$step}.".($index + 1),
				'target' => $target,
				...$extra,
			]);
		}

		return $quest;
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

	public function test_finishing_the_last_step_gives_the_xp_and_the_accessory(): void
	{
		$this->quest(['name' => 'Couronnement', 'reward_xp' => 800, 'reward_accessory' => 'crown'], [[1, 'steps', 1000]]);
		$this->myQuests();

		$this->walked('2026-10-14', 1000);

		$this->myQuests()
			->assertJsonCount(0, 'quests')
			->assertJsonPath('completed.0.name', 'Couronnement')
			->assertJsonPath('completed.0.reward', ['xp' => 800, 'accessory' => 'crown']);

		$this->getJson('/api/me')
			->assertJsonPath('companion.xp', 100 + 800)
			->assertJsonPath('companion.unlocked_accessories', ['crown']);

		$this->putJson('/api/me/companion', ['name' => 'Bulle', 'species' => 'scuba', 'color' => 'ocean', 'accessories' => ['crown']])->assertOk();
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
			->assertJsonPath('status', 'in_progress')
			->assertJsonPath('quest.tasks.0.completed', true);

		$this->act($userQuest['id'], $parkTask->id, ['latitude' => 43.6956, 'longitude' => 7.2690])
			->assertOk()
			->assertJsonPath('status', 'completed')
			->assertJsonPath('quest', null)
			->assertJsonPath('reward.xp', 100);
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

		$this->myQuests()->assertJsonCount(0, 'quests')->assertJsonCount(0, 'completed');
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
		$this->myQuests()->assertJsonCount(0, 'quests')->assertJsonPath('completed.0.name', 'Rencontre');
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
