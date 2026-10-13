<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserPositionFactory extends Factory
{
	public function definition(): array
	{
		return [
			'user_id' => User::factory(),
			'latitude' => 43.6953,
			'longitude' => 7.2806,
			'accuracy_m' => 10,
			'mocked' => false,
			'recorded_at' => now(),
		];
	}
}
