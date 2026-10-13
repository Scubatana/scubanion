<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use LogicException;

#[Fillable(['quest_id', 'step', 'quest_task_id', 'xp', 'accessory_id'])]
class QuestReward extends Model
{
	protected static function booted(): void
	{
		static::saving(function (QuestReward $reward): void {
			if ($reward->quest->isStarted()) {
				throw new LogicException('The rewards of a started quest cannot change.');
			}

			if ($reward->quest_task_id !== null && $reward->task->quest_id !== $reward->quest_id) {
				throw new InvalidArgumentException('A task reward must point to a task of the same quest.');
			}

			if ($reward->quest_task_id !== null) {
				$reward->step = null;
			}

			if ($reward->step !== null && ($reward->step < 1 || $reward->step > $reward->quest->stepCount())) {
				throw new InvalidArgumentException('A step reward must point to an existing step of the quest.');
			}

			if ($reward->accessory_id !== null && ! $reward->accessory->earnable) {
				throw new InvalidArgumentException('Only an earnable accessory can be given as a reward.');
			}

			if ($reward->xp <= 0 && $reward->accessory_id === null) {
				throw new InvalidArgumentException('A reward must give experience, an accessory or both.');
			}
		});

		static::deleting(function (QuestReward $reward): void {
			if ($reward->quest->isStarted()) {
				throw new LogicException('The rewards of a started quest cannot change.');
			}
		});
	}

	protected function casts(): array
	{
		return [
			'step' => 'integer',
			'xp' => 'integer',
		];
	}

	public function quest(): BelongsTo
	{
		return $this->belongsTo(Quest::class);
	}

	public function task(): BelongsTo
	{
		return $this->belongsTo(QuestTask::class, 'quest_task_id');
	}

	public function accessory(): BelongsTo
	{
		return $this->belongsTo(Accessory::class);
	}

	#[Scope]
	protected function forTask(Builder $query, QuestTask $task): void
	{
		$query->where('quest_task_id', $task->id);
	}

	#[Scope]
	protected function forStep(Builder $query, int $step): void
	{
		$query->whereNull('quest_task_id')->where('step', $step);
	}

	#[Scope]
	protected function forQuestEnd(Builder $query): void
	{
		$query->whereNull('quest_task_id')->whereNull('step');
	}
}
