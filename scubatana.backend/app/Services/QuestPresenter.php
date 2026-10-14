<?php

namespace App\Services;

use App\Enums\AppAction;
use App\Models\PlacePoint;
use App\Models\QuestReward;
use App\Models\UserQuest;
use App\Models\UserQuestTask;
use App\Models\UserReward;
use Illuminate\Database\Eloquent\Builder;

class QuestPresenter
{
	public function __construct(private QuestEngine $engine) {}

	public function present(UserQuest $userQuest): array
	{
		$quest = $userQuest->quest;
		$active = $userQuest->isActiveAt(now());

		return [
			'id' => $userQuest->id,
			'quest_id' => $quest->id,
			'name' => $quest->name,
			'description' => $quest->description,
			'icon' => $quest->icon,
			'period' => $userQuest->period,
			'expires_at' => $userQuest->expires_at?->toIso8601String(),
			'status' => $this->status($userQuest),
			'step' => $userQuest->current_step,
			'steps' => $quest->stepCount(),
			'tasks' => $active ? $this->engine->currentTasks($userQuest)->map(fn (UserQuestTask $userTask) => $this->task($userTask))->all() : [],
			'step_rewards' => $active ? $this->rewards(QuestReward::where('quest_id', $quest->id)->forStep($userQuest->current_step)) : [],
			'quest_rewards' => $this->rewards(QuestReward::where('quest_id', $quest->id)->forQuestEnd()),
			'rewards_to_claim' => $userQuest->rewards()->unclaimed()->with('accessory')->orderBy('id')->get()
				->map(fn (UserReward $reward) => ['id' => $reward->id, 'xp' => $reward->xp, 'accessory' => $reward->accessory?->code])->all(),
		];
	}

	public function status(UserQuest $userQuest): string
	{
		return match (true) {
			$userQuest->failed_at !== null => 'failed',
			$userQuest->completed_at !== null => 'completed',
			$userQuest->isActiveAt(now()) => 'active',
			default => 'expired',
		};
	}

	private function rewards(Builder $rewards): array
	{
		return $rewards->with('accessory')->orderBy('id')->get()
			->map(fn (QuestReward $reward) => ['xp' => $reward->xp, 'accessory' => $reward->accessory?->code])->all();
	}

	private function task(UserQuestTask $userTask): array
	{
		$task = $userTask->questTask;
		$action = $task->type->app_action;
		$questions = $action === AppAction::Answer ? ($task->params['questions'] ?? []) : null;

		return [
			'id' => $task->id,
			'type' => $task->type->code,
			'action' => $action->value,
			'description' => $task->description,
			'target' => $questions === null ? $task->target : count($questions),
			'value' => $userTask->value,
			'completed' => $userTask->isCompleted(),
			'place' => $task->place === null ? null : [
				'id' => $task->place->id,
				'name' => $task->place->name,
				'latitude' => $task->place->latitude,
				'longitude' => $task->place->longitude,
				'radius_m' => $task->place->points->isEmpty() ? $task->place->effectiveRadius() : null,
				'outline' => $task->place->points->isEmpty() ? null : $task->place->points->map(fn (PlacePoint $point) => [$point->latitude, $point->longitude])->all(),
			],
			'place_type' => $task->placeType === null ? null : ['code' => $task->placeType->code, 'label' => $task->placeType->label],
			'questions' => $questions === null ? null : array_map(fn (array $question) => ['question' => $question['question'], 'choices' => $question['choices']], $questions),
			'rewards' => $task->rewards->sortBy('id')->map(fn (QuestReward $reward) => ['xp' => $reward->xp, 'accessory' => $reward->accessory?->code])->values()->all(),
		];
	}
}
