<?php

namespace Database\Factories;

use App\Enums\TerminalStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class TerminalFactory extends Factory
{
	public function definition(): array
	{
		return [
			'code' => 'borne-'.fake()->unique()->numerify('###'),
			'name' => 'Borne du parc',
			'place_id' => null,
			'status' => TerminalStatus::Offline,
			'firmware' => null,
			'last_seen_at' => null,
		];
	}
}
