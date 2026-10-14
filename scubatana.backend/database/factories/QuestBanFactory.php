<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestBanFactory extends Factory
{
	public function definition(): array
	{
		return [
			'user_id' => User::factory(),
			'reason' => 'teleport',
			'evidence' => null,
			'starts_at' => now(),
			'ends_at' => now()->addDay(),
		];
	}
}
