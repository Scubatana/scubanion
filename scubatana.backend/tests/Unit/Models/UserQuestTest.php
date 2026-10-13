<?php

namespace Tests\Unit\Models;

use App\Models\Quest;
use App\Models\QuestBan;
use App\Models\QuestTask;
use App\Models\User;
use App\Models\UserAccessory;
use App\Models\UserPosition;
use App\Models\UserQuest;
use App\Models\UserQuestTask;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserQuestTest extends TestCase
{
	use RefreshDatabase;

	public function test_a_quest_is_given_once_per_period(): void
	{
		$given = UserQuest::factory()->create(['period' => '2026-W42']);
		UserQuest::factory()->create(['user_id' => $given->user_id, 'quest_id' => $given->quest_id, 'period' => '2026-W43']);

		$this->expectException(UniqueConstraintViolationException::class);
		UserQuest::factory()->create(['user_id' => $given->user_id, 'quest_id' => $given->quest_id, 'period' => '2026-W42']);
	}

	public function test_only_running_quests_are_active(): void
	{
		$this->travelTo('2026-10-14 12:00:00');
		$running = UserQuest::factory()->create(['expires_at' => '2026-10-15 00:00:00']);
		$forever = UserQuest::factory()->create();
		UserQuest::factory()->create(['expires_at' => '2026-10-14 00:00:00']);
		UserQuest::factory()->create(['completed_at' => now()]);
		UserQuest::factory()->create(['failed_at' => now()]);

		$this->assertSame([$running->id, $forever->id], UserQuest::activeAt(now())->orderBy('id')->pluck('id')->all());
		$this->assertTrue($running->isActiveAt(now()));
		$this->assertFalse($running->isActiveAt(now()->addDay()));
	}

	public function test_a_ban_only_blocks_location_tasks_while_it_lasts(): void
	{
		$this->travelTo('2026-10-14 12:00:00');
		$player = User::factory()->create();
		QuestBan::factory()->for($player)->create(['starts_at' => now(), 'ends_at' => now()->addDay()]);

		$this->assertTrue($player->isBannedFromLocationTasks());

		$this->travelTo('2026-10-15 12:00:01');
		$this->assertFalse($player->isBannedFromLocationTasks());
	}

	public function test_deleting_a_player_deletes_their_quests_progress_accessories_position_and_bans(): void
	{
		$player = User::factory()->create();
		$quest = Quest::factory()->create();
		$userQuest = UserQuest::factory()->for($player)->for($quest)->create();
		UserQuestTask::factory()->for($userQuest)->create(['quest_task_id' => QuestTask::factory()->for(Quest::factory()->draft())]);
		UserAccessory::create(['user_id' => $player->id, 'accessory' => 'crown', 'quest_id' => $quest->id, 'unlocked_at' => now()]);
		UserPosition::factory()->for($player)->create();
		QuestBan::factory()->for($player)->create();

		$player->delete();

		$this->assertSame(0, UserQuest::count() + UserQuestTask::count() + UserAccessory::count() + UserPosition::count() + QuestBan::count());
		$this->assertModelExists($quest);
	}

	public function test_a_player_has_a_single_last_known_position(): void
	{
		$position = UserPosition::factory()->create();

		$this->expectException(QueryException::class);
		UserPosition::factory()->create(['user_id' => $position->user_id]);
	}

	public function test_a_task_someone_is_doing_cannot_be_deleted(): void
	{
		$task = QuestTask::factory()->for(Quest::factory()->draft())->create();
		UserQuestTask::factory()->create(['quest_task_id' => $task->id]);

		$this->expectException(QueryException::class);
		$task->quest->delete();
	}
}
