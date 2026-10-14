<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['terminal_id', 'user_id', 'token_hash', 'scanned_at', 'reward_xp'])]
class Passage extends Model
{
	use HasFactory;

	protected function casts(): array
	{
		return [
			'scanned_at' => 'datetime',
			'reward_xp' => 'integer',
		];
	}

	public function terminal(): BelongsTo
	{
		return $this->belongsTo(Terminal::class);
	}

	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}

	public function isRewarded(): bool
	{
		return $this->reward_xp > 0;
	}
}
