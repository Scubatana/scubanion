<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PlaceTypeFactory extends Factory
{
	public function definition(): array
	{
		return [
			'code' => 'type_'.fake()->unique()->numerify('###'),
			'label' => 'Type de lieu',
			'icon' => null,
			'color' => null,
			'on_map' => true,
			'active' => true,
		];
	}
}
