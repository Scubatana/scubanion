<?php

namespace Database\Factories;

use App\Models\Quest;
use App\Models\TaskType;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestTaskFactory extends Factory
{
	public function definition(): array
	{
		return [
			'quest_id' => Quest::factory(),
			'step' => 1,
			'position' => 1,
			'task_type_id' => fn () => TaskType::where('code', 'steps')->value('id'),
			'description' => 'Marche 5 000 pas',
			'target' => 5000,
			'place_id' => null,
			'place_type_id' => null,
			'params' => null,
		];
	}

	public function ofType(string $code): static
	{
		return $this->state(fn (array $attributes) => ['task_type_id' => TaskType::where('code', $code)->value('id')]);
	}
}
