<?php

namespace Database\Factories;

use App\Models\Quest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestProgressFactory extends Factory
{
	public function definition(): array
	{
		return [
			'user_id' => User::factory(),
			'quest_id' => Quest::factory(),
			'progress' => 0,
			'completed_at' => null,
		];
	}
}
