<?php

namespace Database\Factories;

use App\Models\QuestTask;
use App\Models\UserQuest;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserQuestTaskFactory extends Factory
{
	public function definition(): array
	{
		return [
			'user_quest_id' => UserQuest::factory(),
			'quest_task_id' => QuestTask::factory(),
			'value' => 0,
			'baseline' => 0,
			'completed_at' => null,
		];
	}
}
