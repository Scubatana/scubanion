<?php

namespace App\Models;

use Database\Factories\CompanionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'name', 'species', 'color', 'accessories', 'level', 'xp', 'mood', 'health'])]
class Companion extends Model
{
	use HasFactory;

	protected function casts(): array
	{
		return [
			'accessories' => 'array',
			'level' => 'integer',
			'xp' => 'integer',
			'mood' => 'integer',
			'health' => 'integer',
		];
	}

	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}
}
