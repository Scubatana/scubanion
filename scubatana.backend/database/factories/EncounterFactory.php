<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EncounterFactory extends Factory
{
	public function definition(): array
	{
		return [
			'user_id' => User::factory(),
			'other_user_id' => User::factory(),
			'met_at' => now(),
			'zone' => 'spv2bc',
			'validated_at' => null,
		];
	}
}
