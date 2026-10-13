<?php

namespace App\Services;

use App\Enums\AppAction;
use App\Models\PlacePoint;
use App\Models\UserQuest;
use App\Models\UserQuestTask;

class QuestPresenter
{
	public function __construct(private QuestEngine $engine) {}

	public function active(UserQuest $userQuest): array
	{
		$quest = $userQuest->quest;

		return [
			'id' => $userQuest->id,
			'quest_id' => $quest->id,
			'name' => $quest->name,
			'description' => $quest->description,
			'icon' => $quest->icon,
			'period' => $userQuest->period,
			'expires_at' => $userQuest->expires_at?->toIso8601String(),
			'step' => $userQuest->current_step,
			'steps' => $quest->stepCount(),
			'reward' => ['xp' => $quest->reward_xp, 'accessory' => $quest->reward_accessory],
			'tasks' => $this->engine->currentTasks($userQuest)->map(fn (UserQuestTask $userTask) => $this->task($userTask))->all(),
		];
	}

	public function completed(UserQuest $userQuest): array
	{
		return [
			'id' => $userQuest->id,
			'quest_id' => $userQuest->quest_id,
			'name' => $userQuest->quest->name,
			'icon' => $userQuest->quest->icon,
			'completed_at' => $userQuest->completed_at->toIso8601String(),
			'reward' => ['xp' => $userQuest->reward_xp, 'accessory' => $userQuest->quest->reward_accessory],
		];
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
				'radius_m' => $task->place->points->isEmpty() ? ($task->place->radius_m ?? config('places.default_radius_m')) : null,
				'outline' => $task->place->points->isEmpty() ? null : $task->place->points->map(fn (PlacePoint $point) => [$point->latitude, $point->longitude])->all(),
			],
			'place_type' => $task->placeType === null ? null : ['code' => $task->placeType->code, 'label' => $task->placeType->label],
			'questions' => $questions === null ? null : array_map(fn (array $question) => ['question' => $question['question'], 'choices' => $question['choices']], $questions),
		];
	}
}
