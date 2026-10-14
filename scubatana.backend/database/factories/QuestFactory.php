<?php

namespace Database\Factories;

use App\Enums\QuestRepeat;
use App\Enums\QuestStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestFactory extends Factory
{
	public function definition(): array
	{
		return [
			'name' => 'Premiers pas',
			'description' => null,
			'icon' => null,
			'auto_assign' => false,
			'target_ambition' => null,
			'target_zone_id' => null,
			'repeat' => QuestRepeat::Once,
			'starts_at' => null,
			'ends_at' => null,
			'status' => QuestStatus::Published,
		];
	}

	public function draft(): static
	{
		return $this->state(fn (array $attributes) => ['status' => QuestStatus::Draft]);
	}
}
