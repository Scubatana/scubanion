<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'latitude', 'longitude', 'accuracy_m', 'mocked', 'recorded_at'])]
class UserPosition extends Model
{
	use HasFactory;

	protected function casts(): array
	{
		return [
			'latitude' => 'float',
			'longitude' => 'float',
			'accuracy_m' => 'integer',
			'mocked' => 'boolean',
			'recorded_at' => 'datetime',
		];
	}

	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}
}
