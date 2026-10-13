<?php

namespace Tests\Unit\Models;

use App\Models\Quest;
use App\Models\QuestProgress;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestProgressTest extends TestCase
{
	use RefreshDatabase;

	public function test_a_player_follows_each_quest_once(): void
	{
		$progress = QuestProgress::factory()->create();

		$this->expectException(UniqueConstraintViolationException::class);

		QuestProgress::factory()->create(['user_id' => $progress->user_id, 'quest_id' => $progress->quest_id]);
	}

	public function test_it_is_completed_once_it_has_a_completion_date(): void
	{
		$progress = QuestProgress::factory()->create(['progress' => 4200]);

		$this->assertFalse($progress->isCompleted());

		$progress->update(['progress' => 5000, 'completed_at' => now()]);

		$this->assertTrue($progress->fresh()->isCompleted());
	}

	public function test_the_progress_of_a_player_goes_away_with_the_player(): void
	{
		$user = User::factory()->create();
		QuestProgress::factory()->for($user)->create();
		QuestProgress::factory()->create();

		$this->assertSame(1, $user->questProgress()->count());

		$user->delete();

		$this->assertSame(1, QuestProgress::count());
		$this->assertSame(2, Quest::count());
	}
}
