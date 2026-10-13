<?php

namespace Database\Factories;

use App\Models\Terminal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PassageFactory extends Factory
{
	public function definition(): array
	{
		return [
			'terminal_id' => Terminal::factory(),
			'user_id' => User::factory(),
			'client_id' => (string) Str::uuid(),
			'scanned_at' => now(),
			'reward_xp' => 100,
		];
	}
}
