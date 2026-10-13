<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'quest_id', 'progress', 'completed_at'])]
class QuestProgress extends Model
{
	use HasFactory;

	protected function casts(): array
	{
		return [
			'progress' => 'integer',
			'completed_at' => 'datetime',
		];
	}

	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}

	public function quest(): BelongsTo
	{
		return $this->belongsTo(Quest::class);
	}

	public function isCompleted(): bool
	{
		return $this->completed_at !== null;
	}
}
