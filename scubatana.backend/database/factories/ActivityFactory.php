<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityFactory extends Factory
{
	public function definition(): array
	{
		return [
			'user_id' => User::factory(),
			'day' => now()->toDateString(),
			'device_id' => 'unknown',
			'steps' => 0,
			'source' => 'phone',
		];
	}
}
