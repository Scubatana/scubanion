<?php

namespace Tests\Unit\Models;

use App\Enums\Ambition;
use App\Enums\QuestRepeat;
use App\Enums\QuestStatus;
use App\Models\Accessory;
use App\Models\Place;
use App\Models\Quest;
use App\Models\QuestReward;
use App\Models\QuestTask;
use App\Models\UserQuest;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class QuestTest extends TestCase
{
	use RefreshDatabase;

	private function questWithSteps(): Quest
	{
		$quest = Quest::factory()->create(['repeat' => QuestRepeat::Weekly, 'target_ambition' => Ambition::Explore]);
		QuestTask::factory()->for($quest)->create(['step' => 1, 'position' => 1]);
		QuestTask::factory()->for($quest)->ofType('visit_place')->create(['step' => 1, 'position' => 2, 'target' => 1, 'place_id' => Place::factory()]);
		QuestTask::factory()->for($quest)->ofType('quiz')->create(['step' => 2, 'position' => 1, 'target' => 2]);

		return $quest->fresh();
	}

	public function test_a_quest_is_made_of_steps_holding_several_tasks(): void
	{
		$quest = $this->questWithSteps();

		$this->assertSame(2, $quest->stepCount());
		$this->assertSame([[1, 1], [1, 2], [2, 1]], $quest->tasks->map(fn (QuestTask $task) => [$task->step, $task->position])->all());
		$this->assertSame('visit_place', $quest->tasks[1]->type->code);
		$this->assertSame(QuestRepeat::Weekly, $quest->repeat);
		$this->assertSame(Ambition::Explore, $quest->target_ambition);
	}

	public function test_available_at_keeps_the_published_quests_of_the_moment(): void
	{
		$this->travelTo('2026-10-14 12:00:00');
		$permanent = Quest::factory()->create();
		$thisWeek = Quest::factory()->create(['starts_at' => '2026-10-12 00:00:00', 'ends_at' => '2026-10-19 00:00:00']);
		Quest::factory()->create(['starts_at' => '2026-10-19 00:00:00']);
		Quest::factory()->create(['ends_at' => '2026-10-12 00:00:00']);
		Quest::factory()->draft()->create();
		Quest::factory()->create(['status' => QuestStatus::Archived]);

		$this->assertSame([$permanent->id, $thisWeek->id], Quest::availableAt(now())->orderBy('id')->pluck('id')->all());
	}

	public function test_a_started_quest_only_changes_its_texts_and_status(): void
	{
		$quest = $this->questWithSteps();
		UserQuest::factory()->for($quest)->create();

		$quest->update(['name' => 'Nouveau nom', 'description' => 'Nouveau texte', 'status' => QuestStatus::Archived]);
		$quest->tasks[0]->update(['description' => 'Marche un peu']);
		$this->assertSame('Nouveau nom', $quest->fresh()->name);

		$this->assertThrows(fn () => $quest->update(['repeat' => QuestRepeat::Daily]), LogicException::class);
		$this->assertThrows(fn () => $quest->rewards()->create(['xp' => 999]), LogicException::class);
		$this->assertThrows(fn () => $quest->tasks[0]->update(['target' => 1]), LogicException::class);
		$this->assertThrows(fn () => $quest->tasks[0]->delete(), LogicException::class);
		$this->assertThrows(fn () => QuestTask::factory()->for($quest)->create(['step' => 3]), LogicException::class);
		$this->assertThrows(fn () => $quest->delete(), QueryException::class);
	}

	public function test_a_quest_nobody_received_can_be_changed_and_deleted_once_its_tasks_are_removed(): void
	{
		$quest = $this->questWithSteps();

		$quest->update(['repeat' => QuestRepeat::Daily]);
		$quest->tasks[0]->update(['target' => 8000]);

		$this->assertThrows(fn () => $quest->delete(), QueryException::class);

		$quest->rewards()->create(['xp' => 100]);
		$this->assertThrows(fn () => $quest->delete(), QueryException::class);

		$quest->rewards->each->delete();
		$quest->tasks->each->delete();
		$quest->delete();

		$this->assertModelMissing($quest);
	}

	public function test_a_reward_belongs_to_a_task_a_step_or_the_end_of_the_quest(): void
	{
		$quest = $this->questWithSteps();
		$crown = Accessory::where('code', 'crown')->first();
		$taskReward = $quest->rewards()->create(['quest_task_id' => $quest->tasks[1]->id, 'step' => 2, 'accessory_id' => $crown->id]);
		$stepReward = $quest->rewards()->create(['step' => 1, 'xp' => 50]);
		$endReward = $quest->rewards()->create(['xp' => 300]);

		$this->assertNull($taskReward->step);
		$this->assertTrue(QuestReward::forTask($quest->tasks[1])->sole()->is($taskReward));
		$this->assertTrue(QuestReward::where('quest_id', $quest->id)->forStep(1)->sole()->is($stepReward));
		$this->assertTrue(QuestReward::where('quest_id', $quest->id)->forQuestEnd()->sole()->is($endReward));
		$this->assertThrows(fn () => $crown->delete(), QueryException::class);

		$other = Quest::factory()->create();
		$this->assertThrows(fn () => $other->rewards()->create(['quest_task_id' => $quest->tasks[0]->id, 'xp' => 10]), InvalidArgumentException::class);
	}

	public function test_a_reward_that_could_never_be_earned_is_refused(): void
	{
		$quest = $this->questWithSteps();

		$this->assertThrows(fn () => $quest->rewards()->create(['step' => 3, 'xp' => 50]), InvalidArgumentException::class);
		$this->assertThrows(fn () => $quest->rewards()->create(['step' => 0, 'xp' => 50]), InvalidArgumentException::class);
		$this->assertThrows(fn () => $quest->rewards()->create(['accessory_id' => Accessory::where('code', 'tophat')->value('id')]), InvalidArgumentException::class);
		$this->assertThrows(fn () => $quest->rewards()->create(['step' => 2]), InvalidArgumentException::class);
		$this->assertSame(0, $quest->rewards()->count());
	}

	public function test_a_place_used_by_a_quest_cannot_be_deleted(): void
	{
		$zone = Place::factory()->ofType('zone')->create();
		Quest::factory()->create(['target_zone_id' => $zone->id]);

		$this->expectException(QueryException::class);
		$zone->delete();
	}
}
