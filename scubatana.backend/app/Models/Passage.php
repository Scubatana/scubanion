<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['terminal_id', 'user_id', 'token_hash', 'scanned_at', 'reward_xp', 'accessory_id'])]
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

	public function accessory(): BelongsTo
	{
		return $this->belongsTo(Accessory::class);
	}

	public function eventPassages(): HasMany
	{
		return $this->hasMany(EventPassage::class);
	}

	public function isRewarded(): bool
	{
		return $this->reward_xp > 0;
	}
}
