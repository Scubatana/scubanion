<?php

namespace Database\Factories;

use App\Enums\AppAction;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskTypeFactory extends Factory
{
	public function definition(): array
	{
		return [
			'code' => 'task_'.fake()->unique()->numerify('###'),
			'label' => 'Type de tâche',
			'app_action' => AppAction::Auto,
			'params_schema' => null,
			'active' => true,
		];
	}
}
