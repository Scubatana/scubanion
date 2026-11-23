<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
	public function definition(): array
	{
		return [
			'name' => 'Week-end au parc',
			'description' => null,
			'starts_at' => now()->subHour(),
			'ends_at' => now()->addDay(),
			'bonus_xp' => 50,
			'accessory_id' => null,
			'all_terminals' => true,
			'active' => true,
		];
	}

	public function between(string $startsAt, string $endsAt): static
	{
		return $this->state(fn (array $attributes) => ['starts_at' => $startsAt, 'ends_at' => $endsAt]);
	}
}
