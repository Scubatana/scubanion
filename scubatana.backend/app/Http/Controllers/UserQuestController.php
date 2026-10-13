<?php

namespace App\Http\Controllers;

use App\Enums\AppAction;
use App\Models\QuestTask;
use App\Models\UserQuest;
use App\Models\UserQuestTask;
use App\Services\QuestAssigner;
use App\Services\QuestEngine;
use App\Services\QuestPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserQuestController extends Controller
{
	private const OFFLINE_MAX_HOURS = 24;

	private const CLOCK_TOLERANCE_MINUTES = 5;

	private const RECENT_DAYS = 7;

	public function index(Request $request, QuestAssigner $assigner, QuestEngine $engine, QuestPresenter $presenter): JsonResponse
	{
		$player = $request->user();
		$assigner->assign($player, now());

		$active = $player->userQuests()->activeAt(now())->with('quest')->orderBy('id')->get()
			->each(fn (UserQuest $userQuest) => $engine->refresh($userQuest))
			->filter(fn (UserQuest $userQuest) => $userQuest->isActiveAt(now()));

		$completed = $player->userQuests()->with('quest')
			->where('completed_at', '>=', now()->subDays(self::RECENT_DAYS))
			->orderByDesc('completed_at')
			->get();

		return response()->json([
			'quests' => $active->map(fn (UserQuest $userQuest) => $presenter->active($userQuest))->values()->all(),
			'completed' => $completed->map(fn (UserQuest $userQuest) => $presenter->completed($userQuest))->all(),
		]);
	}

	public function act(Request $request, UserQuest $userQuest, QuestTask $questTask, QuestEngine $engine, QuestPresenter $presenter): JsonResponse
	{
		abort_unless($userQuest->user_id === $request->user()->id && $questTask->quest_id === $userQuest->quest_id && $questTask->step === $userQuest->current_step, 404);

		$userTask = $userQuest->tasks()->where('quest_task_id', $questTask->id)->firstOrFail();
		$recordedAt = $this->recordedAt($request);

		if ($userQuest->completed_at !== null || $userQuest->failed_at !== null || ! $userQuest->isActiveAt($recordedAt)) {
			return response()->json(['message' => "Cette quête n'est plus disponible."], 409);
		}

		if ($userTask->isCompleted()) {
			return response()->json(['message' => 'Cette tâche est déjà faite.'], 409);
		}

		match ($questTask->type->app_action) {
			AppAction::Checkin => $this->checkIn($request, $engine, $userQuest, $userTask),
			AppAction::Scan => $this->scan($request, $engine, $userQuest, $userTask),
			AppAction::Answer => $this->answer($request, $engine, $userQuest, $userTask),
			AppAction::Auto, AppAction::Meet => abort(response()->json(['message' => 'Cette tâche avance toute seule.'], 409)),
		};

		$userQuest->refresh();

		return response()->json([
			'status' => match (true) {
				$userQuest->failed_at !== null => 'failed',
				$userQuest->completed_at !== null => 'completed',
				default => 'in_progress',
			},
			'quest' => $userQuest->failed_at === null && $userQuest->completed_at === null ? $presenter->active($userQuest) : null,
			'reward' => $userQuest->completed_at !== null ? $presenter->completed($userQuest)['reward'] : null,
		]);
	}

	private function recordedAt(Request $request): CarbonImmutable
	{
		$request->validate(['recorded_at' => ['nullable', 'date']]);

		if (! $request->filled('recorded_at')) {
			return CarbonImmutable::now();
		}

		$recordedAt = CarbonImmutable::parse($request->input('recorded_at'))->setTimezone(config('app.timezone'));

		if ($recordedAt->lt(now()->subHours(self::OFFLINE_MAX_HOURS)) || $recordedAt->gt(now()->addMinutes(self::CLOCK_TOLERANCE_MINUTES))) {
			throw ValidationException::withMessages(['recorded_at' => 'Cette validation est trop ancienne.']);
		}

		return $recordedAt;
	}

	private function checkIn(Request $request, QuestEngine $engine, UserQuest $userQuest, UserQuestTask $userTask): void
	{
		$position = $request->validate([
			'latitude' => ['required', 'numeric', 'between:-90,90'],
			'longitude' => ['required', 'numeric', 'between:-180,180'],
		]);

		if (! $engine->checkIn($userQuest, $userTask, (float) $position['latitude'], (float) $position['longitude'])) {
			throw ValidationException::withMessages(['position' => "Tu n'es pas encore sur place."]);
		}
	}

	private function scan(Request $request, QuestEngine $engine, UserQuest $userQuest, UserQuestTask $userTask): void
	{
		$code = $request->validate(['code' => ['required', 'string', 'max:64']])['code'];

		if (! $engine->scan($userQuest, $userTask, $code)) {
			throw ValidationException::withMessages(['code' => 'Ce code ne correspond pas.']);
		}
	}

	private function answer(Request $request, QuestEngine $engine, UserQuest $userQuest, UserQuestTask $userTask): void
	{
		$answer = $request->validate([
			'question' => ['required', 'integer'],
			'choice' => ['required', 'integer', 'min:0'],
		]);

		if ((int) $answer['question'] !== $userTask->value) {
			throw ValidationException::withMessages(['question' => 'Réponds aux questions dans l\'ordre.']);
		}

		$engine->answer($userQuest, $userTask, (int) $answer['choice']);
	}
}
