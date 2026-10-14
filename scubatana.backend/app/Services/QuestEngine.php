<?php

namespace App\Services;

use App\Enums\AppAction;
use App\Enums\CheckInResult;
use App\Enums\QuestSource;
use App\Models\Encounter;
use App\Models\Place;
use App\Models\Quest;
use App\Models\QuestReward;
use App\Models\QuestTask;
use App\Models\User;
use App\Models\UserAccessory;
use App\Models\UserQuest;
use App\Models\UserQuestTask;
use App\Models\UserReward;
use App\Support\QuestPeriod;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QuestEngine
{
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

	public function exclusively(UserQuest $userQuest, callable $work): mixed
	{
		return DB::transaction(function () use ($userQuest, $work): mixed {
			UserQuest::whereKey($userQuest->id)->lockForUpdate()->first();
			$userQuest->refresh();

			return $work();
		});
	}

	public function currentTasks(UserQuest $userQuest): Collection
	{
		return $userQuest->tasks()
			->with(['questTask.type', 'questTask.place.points', 'questTask.placeType', 'questTask.rewards.accessory'])
			->get()
			->filter(fn (UserQuestTask $userTask) => $userTask->questTask->step === $userQuest->current_step)
			->sortBy(fn (UserQuestTask $userTask) => $userTask->questTask->position)
			->values();
	}

	public function refresh(UserQuest $userQuest, ?array $totals = null): void
	{
		if (! $userQuest->isActiveAt(now())) {
			return;
		}

		$current = $this->currentTasks($userQuest);

		foreach ($current as $userTask) {
			$action = $userTask->questTask->type->app_action;

			if ($userTask->isCompleted() || ! in_array($action, [AppAction::Auto, AppAction::Meet], true)) {
				continue;
			}

			$totals ??= $this->totals($userQuest->user);
			$reached = $action === AppAction::Auto ? $totals['steps'] : $totals['encounters'];
			$this->setValue($userTask, max(0, $reached - $userTask->baseline));
		}

		$this->advance($userQuest, $current, $totals);
	}

	public function refreshPlayer(User $player): void
	{
		$automatic = [AppAction::Auto->value, AppAction::Meet->value];
		$quests = $player->userQuests()->activeAt(now())
			->whereHas('tasks', fn (Builder $tasks) => $tasks->whereNull('completed_at')->whereHas('questTask.type', fn (Builder $type) => $type->whereIn('app_action', $automatic)))
			->with('quest')
			->orderBy('id')
			->get();

		if ($quests->isEmpty()) {
			return;
		}

		$totals = $this->totals($player);

		foreach ($quests as $userQuest) {
			$this->exclusively($userQuest, fn () => $this->refresh($userQuest, $totals));
		}
	}

	public function isVisible(UserQuest $userQuest): bool
	{
		return $userQuest->isActiveAt(now()) || $userQuest->rewards()->unclaimed()->exists();
	}

	public function claim(UserQuest $userQuest): Collection
	{
		$claimed = DB::transaction(function () use ($userQuest): Collection {
			$rewards = $userQuest->rewards()->unclaimed()->with('accessory')->orderBy('id')->lockForUpdate()->get();

			foreach ($rewards as $reward) {
				$reward->update(['claimed_at' => now()]);

				if ($reward->accessory_id !== null) {
					UserAccessory::firstOrCreate(
						['user_id' => $userQuest->user_id, 'accessory_id' => $reward->accessory_id],
						['quest_id' => $userQuest->quest_id, 'unlocked_at' => now()],
					);
				}
			}

			return $rewards;
		});

		if ($claimed->isNotEmpty()) {
			$this->progress->refresh($userQuest->user);
		}

		return $claimed;
	}

	public function checkInAround(User $player, float $latitude, float $longitude, ?int $accuracy, CarbonInterface $at): void
	{
		$quests = $player->userQuests()->activeAt($at)
			->whereHas('tasks', fn (Builder $tasks) => $tasks->whereNull('completed_at')->whereHas('questTask.type', fn (Builder $type) => $type->where('app_action', AppAction::Checkin->value)))
			->with('quest')
			->orderBy('id')
			->get();

		foreach ($quests as $userQuest) {
			$this->exclusively($userQuest, function () use ($userQuest, $latitude, $longitude, $accuracy, $at): void {
				if (! $userQuest->isActiveAt($at)) {
					return;
				}

				foreach ($this->currentTasks($userQuest) as $userTask) {
					if (! $userTask->isCompleted() && $userTask->questTask->type->app_action === AppAction::Checkin) {
						$this->checkIn($userQuest, $userTask, $latitude, $longitude, $accuracy);
					}
				}
			});
		}
	}

	public function checkIn(UserQuest $userQuest, UserQuestTask $userTask, float $latitude, float $longitude, ?int $accuracy): CheckInResult
	{
		if ($userQuest->user->isBannedFromLocationTasks()) {
			return CheckInResult::NotThere;
		}

		$places = $this->placesAt($userTask->questTask, $latitude, $longitude, $accuracy);

		if ($places->isEmpty()) {
			return CheckInResult::NotThere;
		}

		$visited = $userTask->visitedPlaces()->pluck('places.id');
		$place = $places->first(fn (Place $place) => ! $visited->contains($place->id));

		if ($place === null) {
			return CheckInResult::AlreadyVisited;
		}

		$userTask->visitedPlaces()->attach($place);
		$this->setValue($userTask, $userTask->questTask->place_id === null ? $userTask->visitedPlaces()->count() : $userTask->questTask->target);
		$this->advance($userQuest);

		return CheckInResult::Validated;
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

	private function unlockStep(UserQuest $userQuest, int $step, ?array $totals = null): void
	{
		foreach ($userQuest->quest->tasks()->with('type')->where('step', $step)->get() as $task) {
			$baseline = match ($task->type->app_action) {
				AppAction::Auto => ($totals ??= $this->totals($userQuest->user))['steps'],
				AppAction::Meet => ($totals ??= $this->totals($userQuest->user))['encounters'],
				default => 0,
			};

			$userQuest->tasks()->create(['quest_task_id' => $task->id, 'baseline' => $baseline]);
		}
	}

	private function totals(User $player): array
	{
		return ['steps' => $this->steps->totalSteps($player), 'encounters' => $this->validatedEncounters($player)];
	}

	private function setValue(UserQuestTask $userTask, int $value, ?int $target = null): void
	{
		$target ??= $userTask->questTask->target;
		$userTask->value = min($target, $value);
		$justCompleted = $userTask->value >= $target && $userTask->completed_at === null;

		if ($justCompleted) {
			$userTask->completed_at = now();
		}

		$userTask->save();

		if ($justCompleted) {
			$this->earn($userTask->userQuest, QuestReward::forTask($userTask->questTask));
		}
	}

	private function advance(UserQuest $userQuest, ?Collection $current = null, ?array $totals = null): void
	{
		if ($userQuest->failed_at !== null || $userQuest->completed_at !== null) {
			return;
		}

		$current ??= $this->currentTasks($userQuest);

		if ($current->isEmpty() || $current->contains(fn (UserQuestTask $userTask) => ! $userTask->isCompleted())) {
			return;
		}

		$this->earn($userQuest, QuestReward::where('quest_id', $userQuest->quest_id)->forStep($userQuest->current_step));
		$next = $userQuest->current_step + 1;

		if (! $userQuest->quest->tasks()->where('step', $next)->exists()) {
			$this->complete($userQuest);

			return;
		}

		$userQuest->update(['current_step' => $next]);
		$this->unlockStep($userQuest, $next, $totals);
	}

	private function complete(UserQuest $userQuest): void
	{
		$userQuest->update(['completed_at' => now()]);
		$this->earn($userQuest, QuestReward::where('quest_id', $userQuest->quest_id)->forQuestEnd());
	}

	private function earn(UserQuest $userQuest, Builder $rewards): void
	{
		foreach ($rewards->get() as $reward) {
			UserReward::firstOrCreate(
				['user_quest_id' => $userQuest->id, 'quest_reward_id' => $reward->id],
				['user_id' => $userQuest->user_id, 'xp' => $reward->xp, 'accessory_id' => $reward->accessory_id, 'earned_at' => now()],
			);
		}
	}

	private function placesAt(QuestTask $task, float $latitude, float $longitude, ?int $accuracy): Collection
	{
		if ($task->place !== null) {
			return $task->place->active && $task->place->contains($latitude, $longitude, $accuracy) ? collect([$task->place]) : collect();
		}

		if ($task->place_type_id === null) {
			return collect();
		}

		return Place::with('points')
			->where('place_type_id', $task->place_type_id)
			->where('active', true)
			->reaching($latitude, $longitude, Place::accuracyMargin($accuracy))
			->orderBy('id')
			->get()
			->filter(fn (Place $place) => $place->contains($latitude, $longitude, $accuracy))
			->values();
	}

	private function validatedEncounters(User $player): int
	{
		return Encounter::involving($player)->whereNotNull('validated_at')->count();
	}
}
