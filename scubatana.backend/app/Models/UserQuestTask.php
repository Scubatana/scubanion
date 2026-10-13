<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_quest_id', 'quest_task_id', 'value', 'baseline', 'completed_at'])]
class UserQuestTask extends Model
{
	use HasFactory;

	protected function casts(): array
	{
		return [
			'value' => 'integer',
			'baseline' => 'integer',
			'completed_at' => 'datetime',
		];
	}

	public function userQuest(): BelongsTo
	{
		return $this->belongsTo(UserQuest::class);
	}

	public function questTask(): BelongsTo
	{
		return $this->belongsTo(QuestTask::class);
	}

	public function isCompleted(): bool
	{
		return $this->completed_at !== null;
	}
}
