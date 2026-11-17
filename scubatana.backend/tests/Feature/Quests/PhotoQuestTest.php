<?php

namespace Tests\Feature\Quests;

use App\Models\Companion;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\Quest;
use App\Models\QuestTask;
use App\Models\TaskType;
use App\Models\User;
use App\Models\UserQuest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhotoQuestTest extends TestCase
{
	use RefreshDatabase;

	private User $lea;

	private Place $castle;

	protected function setUp(): void
	{
		parent::setUp();

		$this->travelTo('2026-11-17 15:00:00');
		$this->lea = User::factory()->create(['pseudo' => 'Lea']);
		Companion::factory()->for($this->lea)->create();
		$this->castle = Place::factory()->create(['name' => 'Colline du Château', 'latitude' => 43.6953, 'longitude' => 7.2806, 'radius_m' => 150]);
		Sanctum::actingAs($this->lea);
	}

	private function photoQuest(string $type, int $target, array $extra): QuestTask
	{
		$quest = Quest::factory()->create(['name' => 'Souvenirs', 'auto_assign' => true]);

		return QuestTask::factory()->for($quest)->create([
			'step' => 1,
			'position' => 1,
			'task_type_id' => TaskType::byCode($type)->id,
			'description' => 'Prends une photo',
			'target' => $target,
			...$extra,
		]);
	}

	private function myQuests()
	{
		return $this->getJson('/api/me/quests')->assertOk();
	}

	private function photo(int $userQuestId, QuestTask $task, array $body)
	{
		return $this->postJson("/api/me/quests/{$userQuestId}/tasks/{$task->id}", $body);
	}

	public function test_a_photo_task_is_sent_to_the_app_with_its_place(): void
	{
		$this->photoQuest('photo_place', 1, ['place_id' => $this->castle->id]);

		$this->myQuests()
			->assertJsonPath('quests.0.tasks.0.type', 'photo_place')
			->assertJsonPath('quests.0.tasks.0.action', 'photo')
			->assertJsonPath('quests.0.tasks.0.place.name', 'Colline du Château')
			->assertJsonPath('quests.0.tasks.0.place.radius_m', 150);
	}

	public function test_a_photo_taken_at_the_place_completes_the_task(): void
	{
		$task = $this->photoQuest('photo_place', 1, ['place_id' => $this->castle->id]);
		$userQuestId = $this->myQuests()->json('quests.0.id');

		$this->photo($userQuestId, $task, ['latitude' => 43.6955, 'longitude' => 7.2809, 'accuracy_m' => 12])
			->assertOk()
			->assertJsonPath('status', 'completed');

		$this->assertNotNull(UserQuest::sole()->completed_at);
		$this->assertSame(43.6955, $this->lea->position()->sole()->latitude);
	}

	public function test_a_photo_taken_elsewhere_is_refused(): void
	{
		$task = $this->photoQuest('photo_place', 1, ['place_id' => $this->castle->id]);
		$userQuestId = $this->myQuests()->json('quests.0.id');

		$this->photo($userQuestId, $task, ['latitude' => 43.7102, 'longitude' => 7.2620])
			->assertUnprocessable()
			->assertJsonValidationErrors(['position' => "Tu n'es pas encore sur place."]);

		$this->photo($userQuestId, $task, [])->assertJsonValidationErrors(['latitude', 'longitude']);
		$this->assertNull(UserQuest::sole()->completed_at);
	}

	public function test_walking_by_the_place_is_not_enough_without_a_photo(): void
	{
		$task = $this->photoQuest('photo_place', 1, ['place_id' => $this->castle->id]);
		$this->myQuests();

		$this->postJson('/api/me/position', ['latitude' => 43.6953, 'longitude' => 7.2806, 'accuracy_m' => 10])->assertNoContent();

		$this->myQuests()->assertJsonPath('quests.0.tasks.0.value', 0)->assertJsonPath('quests.0.tasks.0.completed', false);
		$this->assertSame($task->id, $this->myQuests()->json('quests.0.tasks.0.id'));
	}

	public function test_a_photo_in_any_place_of_a_type_counts_each_place_once(): void
	{
		$park = PlaceType::where('code', 'park')->sole();
		Place::factory()->create(['place_type_id' => $park->id, 'name' => 'Jardin Albert Ier', 'latitude' => 43.6956, 'longitude' => 7.2686, 'radius_m' => 100]);
		Place::factory()->create(['place_type_id' => $park->id, 'name' => 'Parc Phoenix', 'latitude' => 43.6669, 'longitude' => 7.2153, 'radius_m' => 200]);
		$task = $this->photoQuest('photo_place_type', 2, ['place_type_id' => $park->id]);
		$userQuestId = $this->myQuests()->json('quests.0.id');

		$this->photo($userQuestId, $task, ['latitude' => 43.6956, 'longitude' => 7.2686])->assertOk()->assertJsonPath('quest.tasks.0.value', 1);
		$this->photo($userQuestId, $task, ['latitude' => 43.6956, 'longitude' => 7.2686])
			->assertJsonValidationErrors(['position' => 'Tu as déjà validé ce lieu : trouves-en un autre.']);

		$this->travel(2)->hours();
		$this->photo($userQuestId, $task, ['latitude' => 43.6669, 'longitude' => 7.2153])->assertOk()->assertJsonPath('status', 'completed');
	}

	public function test_a_photo_taken_offline_is_accepted_later(): void
	{
		$task = $this->photoQuest('photo_place', 1, ['place_id' => $this->castle->id]);
		$userQuestId = $this->myQuests()->json('quests.0.id');
		$takenAt = now()->toIso8601String();

		$this->travel(3)->hours();

		$this->photo($userQuestId, $task, ['latitude' => 43.6953, 'longitude' => 7.2806, 'recorded_at' => $takenAt])
			->assertOk()
			->assertJsonPath('status', 'completed');
	}

	public function test_a_player_blocked_for_cheating_cannot_validate_a_photo(): void
	{
		$task = $this->photoQuest('photo_place', 1, ['place_id' => $this->castle->id]);
		$userQuestId = $this->myQuests()->json('quests.0.id');

		$this->photo($userQuestId, $task, ['latitude' => 43.6953, 'longitude' => 7.2806, 'mocked' => true])
			->assertJsonValidationErrors(['position' => "Tu n'es pas encore sur place."]);
		$this->assertTrue($this->lea->fresh()->isBannedFromLocationTasks());
	}
}
