<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['place_id', 'position', 'latitude', 'longitude'])]
#[WithoutTimestamps]
class PlacePoint extends Model
{
	protected function casts(): array
	{
		return [
			'position' => 'integer',
			'latitude' => 'float',
			'longitude' => 'float',
		];
	}

	public function place(): BelongsTo
	{
		return $this->belongsTo(Place::class);
	}
}
