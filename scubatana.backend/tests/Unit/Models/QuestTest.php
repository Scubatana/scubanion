<?php

namespace Tests\Unit\Models;

use App\Enums\Ambition;
use App\Enums\QuestRepeat;
use App\Enums\QuestStatus;
use App\Models\Place;
use App\Models\Quest;
use App\Models\QuestTask;
use App\Models\UserQuest;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class QuestTest extends TestCase
{
	use RefreshDatabase;

	private function questWithSteps(): Quest
	{
		$quest = Quest::factory()->create(['repeat' => QuestRepeat::Weekly, 'target_ambition' => Ambition::Explore, 'reward_accessory' => 'crown']);
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

		$this->assertThrows(fn () => $quest->update(['reward_xp' => 999]), LogicException::class);
		$this->assertThrows(fn () => $quest->tasks[0]->update(['target' => 1]), LogicException::class);
		$this->assertThrows(fn () => $quest->tasks[0]->delete(), LogicException::class);
		$this->assertThrows(fn () => QuestTask::factory()->for($quest)->create(['step' => 3]), LogicException::class);
		$this->assertThrows(fn () => $quest->delete(), QueryException::class);
	}

	public function test_a_quest_nobody_received_can_be_changed_and_deleted_with_its_tasks(): void
	{
		$quest = $this->questWithSteps();

		$quest->update(['reward_xp' => 300]);
		$quest->tasks[0]->update(['target' => 8000]);
		$quest->delete();

		$this->assertSame(0, QuestTask::count());
	}

	public function test_a_place_used_by_a_quest_cannot_be_deleted(): void
	{
		$zone = Place::factory()->ofType('zone')->create();
		Quest::factory()->create(['target_zone_id' => $zone->id]);

		$this->expectException(QueryException::class);
		$zone->delete();
	}
}
