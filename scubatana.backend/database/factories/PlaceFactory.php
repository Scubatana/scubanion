<?php

namespace Database\Factories;

use App\Models\PlaceType;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlaceFactory extends Factory
{
	public function definition(): array
	{
		return [
			'place_type_id' => fn () => PlaceType::where('code', 'park')->value('id'),
			'name' => 'Parc '.fake()->unique()->lastName(),
			'latitude' => fake()->latitude(43.65, 43.75),
			'longitude' => fake()->longitude(7.20, 7.30),
			'radius_m' => null,
			'attributes' => null,
			'source' => 'manual',
			'external_id' => null,
			'active' => true,
		];
	}

	public function ofType(string $code): static
	{
		return $this->state(fn (array $attributes) => ['place_type_id' => PlaceType::where('code', $code)->value('id')]);
	}
}
