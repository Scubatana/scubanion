<?php

namespace Database\Factories;

use App\Enums\QuestType;
use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestFactory extends Factory
{
	public function definition(): array
	{
		return [
			'title' => 'Marcher 5 000 pas',
			'description' => null,
			'type' => QuestType::Steps,
			'ambition' => null,
			'goal' => 5000,
			'place_id' => null,
			'reward_xp' => 100,
			'starts_at' => null,
			'ends_at' => null,
		];
	}

	public function atPlace(): static
	{
		return $this->state(fn (array $attributes) => [
			'title' => 'Passer au parc',
			'type' => QuestType::Place,
			'goal' => 1,
			'place_id' => Place::factory(),
		]);
	}
}
