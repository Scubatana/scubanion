<?php

namespace App\Models;

use App\Enums\PlaceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'latitude', 'longitude'])]
class Place extends Model
{
	use HasFactory;

	protected function casts(): array
	{
		return [
			'type' => PlaceType::class,
			'latitude' => 'float',
			'longitude' => 'float',
		];
	}

	public function quests(): HasMany
	{
		return $this->hasMany(Quest::class);
	}
}
