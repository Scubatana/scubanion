<?php

namespace Database\Factories;

use App\Enums\QuestSource;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserQuestFactory extends Factory
{
	public function definition(): array
	{
		return [
			'user_id' => User::factory(),
			'quest_id' => Quest::factory(),
			'period' => 'once',
			'current_step' => 1,
			'source' => QuestSource::Rule,
			'assigned_at' => now(),
			'expires_at' => null,
			'completed_at' => null,
			'failed_at' => null,
			'reward_xp' => 0,
		];
	}
}
