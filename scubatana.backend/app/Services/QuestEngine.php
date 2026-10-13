<?php

namespace App\Services;

use App\Enums\AppAction;
use App\Enums\QuestSource;
use App\Models\Encounter;
use App\Models\Place;
use App\Models\Quest;
use App\Models\QuestTask;
use App\Models\User;
use App\Models\UserAccessory;
use App\Models\UserQuest;
use App\Models\UserQuestTask;
use App\Support\QuestPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class QuestEngine
{
	private const NEARBY_DEGREES = 0.05;

	public function __construct(
		private StepService $steps,
		private CompanionProgressService $progress,
	) {}

	public function give(User $player, Quest $quest, QuestSource $source, CarbonInterface $now): UserQuest
	{
		$period = QuestPeriod::at($quest->repeat, $now);

		$existing = $player->userQuests()->where('quest_id', $quest->id)->where('period', $period->key)->first();

		if ($existing !== null) {
			return $existing;
		}

		$userQuest = $player->userQuests()->create([
			'quest_id' => $quest->id,
			'period' => $period->key,
			'current_step' => 1,
			'source' => $source,
			'assigned_at' => $now,
			'expires_at' => collect([$quest->ends_at, $period->endsAt])->filter()->min(),
		]);

		$this->unlockStep($userQuest, 1);

		return $userQuest;
	}

	public function currentTasks(UserQuest $userQuest): Collection
	{
		return $userQuest->tasks()
			->with(['questTask.type', 'questTask.place.points', 'questTask.placeType'])
			->get()
			->filter(fn (UserQuestTask $userTask) => $userTask->questTask->step === $userQuest->current_step)
			->sortBy(fn (UserQuestTask $userTask) => $userTask->questTask->position)
			->values();
	}

	public function refresh(UserQuest $userQuest): void
	{
		foreach ($this->currentTasks($userQuest) as $userTask) {
			$current = match ($userTask->questTask->type->app_action) {
				AppAction::Auto => $this->steps->totalSteps($userQuest->user),
				AppAction::Meet => $this->validatedEncounters($userQuest->user),
				default => null,
			};

			if ($current !== null && ! $userTask->isCompleted()) {
				$this->setValue($userTask, max(0, $current - $userTask->baseline));
			}
		}

		$this->advance($userQuest);
	}

	public function checkIn(UserQuest $userQuest, UserQuestTask $userTask, float $latitude, float $longitude): bool
	{
		if ($userQuest->user->isBannedFromLocationTasks() || ! $this->isAtTarget($userTask->questTask, $latitude, $longitude)) {
			return false;
		}

		$this->setValue($userTask, $userTask->value + 1);
		$this->advance($userQuest);

		return true;
	}

	public function scan(UserQuest $userQuest, UserQuestTask $userTask, string $code): bool
	{
		$expected = mb_strtoupper(trim((string) ($userTask->questTask->params['code'] ?? '')));

		if ($expected === '' || ! hash_equals($expected, mb_strtoupper(trim($code)))) {
			return false;
		}

		$this->setValue($userTask, $userTask->value + 1);
		$this->advance($userQuest);

		return true;
	}

	public function answer(UserQuest $userQuest, UserQuestTask $userTask, int $choice): bool
	{
		$questions = $userTask->questTask->params['questions'] ?? [];

		if (($questions[$userTask->value]['answer'] ?? null) !== $choice) {
			$userQuest->update(['failed_at' => now()]);

			return false;
		}

		$this->setValue($userTask, $userTask->value + 1, count($questions));
		$this->advance($userQuest);

		return true;
	}

	private function unlockStep(UserQuest $userQuest, int $step): void
	{
		foreach ($userQuest->quest->tasks()->with('type')->where('step', $step)->get() as $task) {
			$userQuest->tasks()->create(['quest_task_id' => $task->id, 'baseline' => $this->baselineFor($userQuest->user, $task)]);
		}
	}

	private function baselineFor(User $player, QuestTask $task): int
	{
		return match ($task->type->app_action) {
			AppAction::Auto => $this->steps->totalSteps($player),
			AppAction::Meet => $this->validatedEncounters($player),
			default => 0,
		};
	}

	private function setValue(UserQuestTask $userTask, int $value, ?int $target = null): void
	{
		$target ??= $userTask->questTask->target;
		$userTask->value = min($target, $value);

		if ($userTask->value >= $target && $userTask->completed_at === null) {
			$userTask->completed_at = now();
		}

		$userTask->save();
	}

	private function advance(UserQuest $userQuest): void
	{
		if ($userQuest->failed_at !== null || $userQuest->completed_at !== null) {
			return;
		}

		$current = $this->currentTasks($userQuest);

		if ($current->isEmpty() || $current->contains(fn (UserQuestTask $userTask) => ! $userTask->isCompleted())) {
			return;
		}

		$next = $userQuest->current_step + 1;

		if (! $userQuest->quest->tasks()->where('step', $next)->exists()) {
			$this->complete($userQuest);

			return;
		}

		$userQuest->update(['current_step' => $next]);
		$this->unlockStep($userQuest, $next);
	}

	private function complete(UserQuest $userQuest): void
	{
		$quest = $userQuest->quest;
		$userQuest->update(['completed_at' => now(), 'reward_xp' => $quest->reward_xp]);

		if ($quest->reward_accessory !== null) {
			UserAccessory::firstOrCreate(
				['user_id' => $userQuest->user_id, 'accessory' => $quest->reward_accessory],
				['quest_id' => $quest->id, 'unlocked_at' => now()],
			);
		}

		$this->progress->refresh($userQuest->user);
	}

	private function isAtTarget(QuestTask $task, float $latitude, float $longitude): bool
	{
		if ($task->place !== null) {
			return $task->place->active && $task->place->contains($latitude, $longitude);
		}

		if ($task->place_type_id === null) {
			return false;
		}

		return Place::with('points')
			->where('place_type_id', $task->place_type_id)
			->where('active', true)
			->where(fn ($nearby) => $nearby
				->where(fn ($point) => $point
					->whereBetween('latitude', [$latitude - self::NEARBY_DEGREES, $latitude + self::NEARBY_DEGREES])
					->whereBetween('longitude', [$longitude - self::NEARBY_DEGREES, $longitude + self::NEARBY_DEGREES]))
				->orWhere(fn ($area) => $area
					->where('min_lat', '<=', $latitude)->where('max_lat', '>=', $latitude)
					->where('min_lng', '<=', $longitude)->where('max_lng', '>=', $longitude)))
			->get()
			->contains(fn (Place $place) => $place->contains($latitude, $longitude));
	}

	private function validatedEncounters(User $player): int
	{
		return Encounter::involving($player)->whereNotNull('validated_at')->count();
	}
}
