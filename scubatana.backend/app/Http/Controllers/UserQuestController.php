<?php

namespace App\Http\Controllers;

use App\Enums\AppAction;
use App\Enums\CheckInResult;
use App\Http\Resources\CompanionResource;
use App\Models\QuestTask;
use App\Models\UserQuest;
use App\Models\UserQuestTask;
use App\Models\UserReward;
use App\Services\QuestAssigner;
use App\Services\QuestEngine;
use App\Services\PositionService;
use App\Services\QuestPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserQuestController extends Controller
{
	private const OFFLINE_MAX_HOURS = 24;

	private const CLOCK_TOLERANCE_MINUTES = 5;

	public function index(Request $request, QuestAssigner $assigner, QuestEngine $engine, QuestPresenter $presenter): JsonResponse
	{
		$player = $request->user();
		$assigner->assign($player, now());
		$engine->refreshPlayer($player);

		$visible = $player->userQuests()->with('quest')
			->where(fn (Builder $shown) => $shown->activeAt(now())->orWhereHas('rewards', fn (Builder $rewards) => $rewards->unclaimed()))
			->orderBy('id')
			->get();

		return response()->json([
			'quests' => $visible->map(fn (UserQuest $userQuest) => $presenter->present($userQuest))->all(),
		]);
	}

	public function act(Request $request, UserQuest $userQuest, QuestTask $questTask, QuestEngine $engine, QuestPresenter $presenter, PositionService $positions): JsonResponse
	{
		abort_unless($userQuest->user_id === $request->user()->id && $questTask->quest_id === $userQuest->quest_id, 404);

		$action = $questTask->type->app_action;

		if ($action === AppAction::Auto || $action === AppAction::Meet) {
			return response()->json(['message' => 'Cette tâche avance toute seule.'], 409);
		}

		$recordedAt = $this->recordedAt($request);
		$input = $this->input($request, $action);

		if ($action === AppAction::Checkin) {
			$positions->record($request->user(), $input['latitude'], $input['longitude'], $input['accuracy'], $input['mocked'], $recordedAt);
		}

		$refusal = $engine->exclusively($userQuest, function () use ($engine, $userQuest, $questTask, $action, $input, $recordedAt): ?JsonResponse {
			abort_unless($questTask->step === $userQuest->current_step, 404);

			$userTask = $userQuest->tasks()->where('quest_task_id', $questTask->id)->firstOrFail();

			if ($userQuest->completed_at !== null || $userQuest->failed_at !== null || ! $userQuest->isActiveAt($recordedAt)) {
				return response()->json(['message' => "Cette quête n'est plus disponible."], 409);
			}

			if ($userTask->isCompleted()) {
				return response()->json(['message' => 'Cette tâche est déjà faite.'], 409);
			}

			match ($action) {
				AppAction::Checkin => $this->checkIn($engine, $userQuest, $userTask, $input),
				AppAction::Scan => $this->scan($engine, $userQuest, $userTask, $input['code']),
				AppAction::Answer => $this->answer($engine, $userQuest, $userTask, $input['question'], $input['choice']),
			};

			return null;
		});

		if ($refusal !== null) {
			return $refusal;
		}

		$userQuest->refresh();

		return response()->json([
			'status' => $presenter->status($userQuest),
			'quest' => $engine->isVisible($userQuest) ? $presenter->present($userQuest) : null,
		]);
	}

	public function claim(Request $request, UserQuest $userQuest, QuestEngine $engine, QuestPresenter $presenter): JsonResponse
	{
		abort_unless($userQuest->user_id === $request->user()->id, 404);

		$claimed = $engine->claim($userQuest);

		if ($claimed->isEmpty()) {
			return response()->json(['message' => 'Rien à récupérer pour cette quête.'], 409);
		}

		$userQuest->refresh();
		$companion = $request->user()->companion()->first();

		return response()->json([
			'claimed' => $claimed->map(fn (UserReward $reward) => ['id' => $reward->id, 'xp' => $reward->xp, 'accessory' => $reward->accessory?->code])->all(),
			'quest' => $engine->isVisible($userQuest) ? $presenter->present($userQuest) : null,
			'companion' => $companion === null ? null : new CompanionResource($companion),
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

	private function input(Request $request, AppAction $action): array
	{
		return match ($action) {
			AppAction::Checkin => $this->position($request),
			AppAction::Scan => $request->validate(['code' => ['required', 'string', 'max:64']]),
			AppAction::Answer => array_map('intval', $request->validate([
				'question' => ['required', 'integer'],
				'choice' => ['required', 'integer', 'min:0'],
			])),
		};
	}

	private function position(Request $request): array
	{
		$position = $request->validate([
			'latitude' => ['required', 'numeric', 'between:-90,90'],
			'longitude' => ['required', 'numeric', 'between:-180,180'],
			'accuracy_m' => ['nullable', 'integer', 'min:0'],
			'mocked' => ['sometimes', 'boolean'],
		]);

		return [
			'latitude' => (float) $position['latitude'],
			'longitude' => (float) $position['longitude'],
			'accuracy' => isset($position['accuracy_m']) ? (int) $position['accuracy_m'] : null,
			'mocked' => $request->boolean('mocked'),
		];
	}

	private function checkIn(QuestEngine $engine, UserQuest $userQuest, UserQuestTask $userTask, array $position): void
	{
		match ($engine->checkIn($userQuest, $userTask, $position['latitude'], $position['longitude'], $position['accuracy'])) {
			CheckInResult::Validated => null,
			CheckInResult::NotThere => throw ValidationException::withMessages(['position' => "Tu n'es pas encore sur place."]),
			CheckInResult::AlreadyVisited => throw ValidationException::withMessages(['position' => 'Tu as déjà validé ce lieu : trouves-en un autre.']),
		};
	}

	private function scan(QuestEngine $engine, UserQuest $userQuest, UserQuestTask $userTask, string $code): void
	{
		if (! $engine->scan($userQuest, $userTask, $code)) {
			throw ValidationException::withMessages(['code' => 'Ce code ne correspond pas.']);
		}
	}

	private function answer(QuestEngine $engine, UserQuest $userQuest, UserQuestTask $userTask, int $question, int $choice): void
	{
		if ($question !== $userTask->value) {
			throw ValidationException::withMessages(['question' => 'Réponds aux questions dans l\'ordre.']);
		}

		$engine->answer($userQuest, $userTask, $choice);
	}
}
