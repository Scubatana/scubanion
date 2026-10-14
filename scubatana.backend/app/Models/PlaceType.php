<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'label', 'icon', 'color', 'on_map', 'active'])]
class PlaceType extends Model
{
	use HasFactory;

	protected function casts(): array
	{
		return [
			'on_map' => 'boolean',
			'active' => 'boolean',
		];
	}

	public function places(): HasMany
	{
		return $this->hasMany(Place::class);
	}
}
