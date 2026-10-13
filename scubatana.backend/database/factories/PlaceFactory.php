<?php

namespace Database\Factories;

use App\Enums\PlaceType;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlaceFactory extends Factory
{
	public function definition(): array
	{
		return [
			'name' => 'Parc '.fake()->unique()->lastName(),
			'type' => PlaceType::Park,
			'latitude' => fake()->latitude(43.65, 43.75),
			'longitude' => fake()->longitude(7.20, 7.30),
		];
	}
}
