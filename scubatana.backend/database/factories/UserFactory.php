<?php

namespace Database\Factories;

use App\Enums\Ambition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
	public function definition(): array
	{
		return [
			'email' => fake()->unique()->safeEmail(),
			'pseudo' => 'joueur'.fake()->unique()->numerify('######'),
			'birth_date' => '2000-01-01',
			'gender' => null,
			'ambition' => Ambition::Fitness,
			'daily_step_goal' => 5000,
			'terms_accepted_at' => now(),
		];
	}

	public function withoutProfile(): static
	{
		return $this->state(fn (array $attributes) => [
			'pseudo' => null,
			'birth_date' => null,
			'gender' => null,
			'ambition' => null,
		]);
	}
}
