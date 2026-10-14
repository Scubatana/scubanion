<?php

namespace App\Models;

use App\Enums\TerminalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'secret', 'place_id', 'status', 'firmware', 'last_seen_at'])]
#[Hidden(['secret'])]
class Terminal extends Model
{
	use HasFactory;

	protected function casts(): array
	{
		return [
			'secret' => 'encrypted',
			'status' => TerminalStatus::class,
			'last_seen_at' => 'datetime',
		];
	}

	public function place(): BelongsTo
	{
		return $this->belongsTo(Place::class);
	}

	public function passages(): HasMany
	{
		return $this->hasMany(Passage::class);
	}

	public function isOnline(): bool
	{
		return $this->status === TerminalStatus::Online
			&& $this->last_seen_at !== null
			&& $this->last_seen_at->gt(now()->subSeconds(config('bornes.offline_after_seconds')));
	}
}
