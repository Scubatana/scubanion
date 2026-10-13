<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['quest_id', 'step', 'position', 'task_type_id', 'description', 'target', 'place_id', 'place_type_id', 'params'])]
class QuestTask extends Model
{
	use HasFactory;

	public const EDITABLE_ONCE_STARTED = ['description', 'updated_at'];

	protected static function booted(): void
	{
		static::creating(function (QuestTask $task): void {
			if ($task->quest?->isStarted()) {
				throw new LogicException('Tasks cannot be added to a started quest.');
			}
		});

		static::updating(function (QuestTask $task): void {
			if ($task->quest->isStarted() && array_diff(array_keys($task->getDirty()), self::EDITABLE_ONCE_STARTED) !== []) {
				throw new LogicException('A task of a started quest can only change its description.');
			}
		});

		static::deleting(function (QuestTask $task): void {
			if ($task->quest->isStarted()) {
				throw new LogicException('Tasks cannot be removed from a started quest.');
			}
		});
	}

	protected function casts(): array
	{
		return [
			'step' => 'integer',
			'position' => 'integer',
			'target' => 'integer',
			'params' => 'array',
		];
	}

	public function rewards(): HasMany
	{
		return $this->hasMany(QuestReward::class);
	}

	public function quest(): BelongsTo
	{
		return $this->belongsTo(Quest::class);
	}

	public function type(): BelongsTo
	{
		return $this->belongsTo(TaskType::class, 'task_type_id');
	}

	public function place(): BelongsTo
	{
		return $this->belongsTo(Place::class);
	}

	public function placeType(): BelongsTo
	{
		return $this->belongsTo(PlaceType::class);
	}
}
