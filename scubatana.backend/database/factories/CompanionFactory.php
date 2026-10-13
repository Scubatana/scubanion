<?php

namespace Database\Factories;

use App\Models\Companion;
use App\Models\User;
use App\Services\CompanionService;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanionFactory extends Factory
{
	public function definition(): array
	{
		return [
			'user_id' => User::factory(),
			'name' => CompanionService::DEFAULT_NAME,
			'species' => CompanionService::DEFAULT_SPECIES,
			'color' => CompanionService::DEFAULT_COLOR,
			'accessories' => [],
			'level' => 1,
			'xp' => 0,
			'mood' => 100,
			'health' => 100,
		];
	}
}
