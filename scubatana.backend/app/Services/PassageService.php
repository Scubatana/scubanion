<?php

namespace App\Services;

use App\Models\Accessory;
use App\Models\Event;
use App\Models\EventPassage;
use App\Models\Passage;
use App\Models\Place;
use App\Models\Terminal;
use App\Models\User;
use App\Models\UserPosition;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PassageService
{
	private const ALREADY_USED = 'Ce code de borne a déjà été utilisé.';

	public function __construct(
		private TerminalTokenService $tokens,
		private CompanionProgressService $progress,
		private PositionService $positions,
	) {}

	public function record(User $player, string $token, ?array $position = null): Passage
	{
		$read = $this->tokens->read($token);

		if ($read === null || $read['scanned_at']->lt(now()->subHours(config('bornes.max_replay_hours'))) || $read['scanned_at']->gt(now()->addMinutes(config('bornes.future_tolerance_minutes')))) {
			throw ValidationException::withMessages(['token' => 'Code de borne invalide ou expiré.']);
		}

		$tokenHash = hash('sha256', $token);
		$terminal = $read['terminal'];
		$scannedAt = $read['scanned_at'];

		if (Passage::where('token_hash', $tokenHash)->exists()) {
			throw new ConflictHttpException(self::ALREADY_USED);
		}

		if ($position !== null) {
			$this->positions->record($player, $position['latitude'], $position['longitude'], $position['accuracy'], $position['mocked'], $position['recorded_at']);
		}

		try {
			$passage = DB::transaction(function () use ($player, $terminal, $scannedAt, $tokenHash, $position): Passage {
				User::whereKey($player->id)->lockForUpdate()->first();

				if (Passage::where('token_hash', $tokenHash)->exists()) {
					throw new ConflictHttpException(self::ALREADY_USED);
				}

				$this->ensurePresent($player, $terminal, $scannedAt, $position);

				$alreadyRewarded = $terminal->passages()
					->where('user_id', $player->id)
					->where('reward_xp', '>', 0)
					->whereBetween('scanned_at', [$scannedAt->startOfDay(), $scannedAt->endOfDay()])
					->exists();

				if ($alreadyRewarded) {
					return $terminal->passages()->create([
						'user_id' => $player->id,
						'token_hash' => $tokenHash,
						'scanned_at' => $scannedAt,
						'reward_xp' => 0,
					]);
				}

				return $this->rewardedPassage($player, $terminal, $scannedAt, $tokenHash);
			});
		} catch (UniqueConstraintViolationException) {
			throw new ConflictHttpException(self::ALREADY_USED);
		}

		if ($passage->isRewarded()) {
			$this->progress->refresh($player);
		}

		return $passage;
	}

	private function rewardedPassage(User $player, Terminal $terminal, CarbonImmutable $scannedAt, string $tokenHash): Passage
	{
		$owned = array_map(intval(...), $player->accessories()->pluck('accessory_id')->all());
		$accessory = $this->newAccessory($terminal->accessory, $owned);
		$given = $accessory === null ? [] : [$accessory->id];
		$eventRewards = [];

		foreach ($this->eventsAt($terminal, $scannedAt) as $event) {
			$eventAccessory = $this->newAccessory($event->accessory, [...$owned, ...$given]);

			if ($eventAccessory !== null) {
				$given[] = $eventAccessory->id;
			}

			if ($event->bonus_xp > 0 || $eventAccessory !== null) {
				$eventRewards[] = ['event_id' => $event->id, 'bonus_xp' => $event->bonus_xp, 'accessory_id' => $eventAccessory?->id];
			}
		}

		foreach ($given as $accessoryId) {
			$player->accessories()->create(['accessory_id' => $accessoryId, 'unlocked_at' => now()]);
		}

		$passage = $terminal->passages()->create([
			'user_id' => $player->id,
			'token_hash' => $tokenHash,
			'scanned_at' => $scannedAt,
			'reward_xp' => config('bornes.reward_xp') + array_sum(array_column($eventRewards, 'bonus_xp')),
			'accessory_id' => $accessory?->id,
		]);
		$passage->eventPassages()->createMany($eventRewards);

		return $passage;
	}

	private function eventsAt(Terminal $terminal, CarbonImmutable $scannedAt): Collection
	{
		return Event::inProgressAt($scannedAt)->atTerminal($terminal)->with('accessory')->orderBy('starts_at')->orderBy('id')->get();
	}

	private function newAccessory(?Accessory $accessory, array $excluded): ?Accessory
	{
		if ($accessory === null || ! $accessory->active || in_array($accessory->id, $excluded, true)) {
			return null;
		}

		return $accessory;
	}

	private function ensurePresent(User $player, Terminal $terminal, CarbonImmutable $scannedAt, ?array $position): void
	{
		$place = $terminal->place;

		if ($place === null) {
			return;
		}

		if ($player->isBannedFromLocationTasks()) {
			throw ValidationException::withMessages(['position' => "Tu n'es pas à la borne : rapproche-toi et scanne-la à nouveau."]);
		}

		if ($position !== null && $this->datesFromTheScan($position['recorded_at'], $scannedAt)) {
			if ($position['mocked'] || ! $place->contains($position['latitude'], $position['longitude'], $position['accuracy'])) {
				throw ValidationException::withMessages(['position' => "Tu n'es pas à la borne : rapproche-toi et scanne-la à nouveau."]);
			}

			return;
		}

		$last = $player->position()->first();

		if ($last === null) {
			throw ValidationException::withMessages(['position' => 'Active ta localisation pour valider la borne, puis scanne-la à nouveau.']);
		}

		if ($last->mocked || ! $this->couldHaveReached($place, $last, $scannedAt)) {
			throw ValidationException::withMessages(['position' => 'Ta dernière position est trop loin de la borne : active ta localisation et scanne-la à nouveau.']);
		}
	}

	private function datesFromTheScan(CarbonImmutable $recordedAt, CarbonImmutable $scannedAt): bool
	{
		$tolerance = config('bornes.future_tolerance_minutes');

		return $recordedAt->gte($scannedAt->subMinutes($tolerance))
			&& $recordedAt->lte($scannedAt->addMinutes(config('bornes.token_refresh_minutes') + $tolerance));
	}

	private function couldHaveReached(Place $place, UserPosition $last, CarbonImmutable $scannedAt): bool
	{
		$distance = $place->distanceOutside($last->latitude, $last->longitude, $last->accuracy_m);
		$seconds = max(
			abs($last->recorded_at->diffInSeconds($scannedAt, false)),
			abs($last->recorded_at->diffInSeconds($scannedAt->addMinutes(config('bornes.token_refresh_minutes')), false)),
		);

		return $distance <= config('bornes.max_travel_speed_kmh') / 3.6 * $seconds;
	}

	public function display(Passage $passage): array
	{
		$pseudo = $passage->user->pseudo ?? 'toi';
		$display = [
			'scanned_at' => $passage->scanned_at->toIso8601String(),
			'status' => $passage->isRewarded() ? 'rewarded' : 'already_rewarded',
			'message' => $passage->isRewarded() ? $this->rewardMessage($passage, $pseudo) : "Ton passage du jour est déjà compté, {$pseudo}. Reviens demain !",
			'reward_xp' => $passage->reward_xp,
		];

		if ($passage->accessory === null) {
			return $display;
		}

		return [
			...$display,
			'accessory' => ['id' => $passage->accessory->code, 'label' => $passage->accessory->label],
		];
	}

	public function events(Passage $passage): array
	{
		return $this->eventRewards($passage)->map(fn (EventPassage $reward) => [
			'id' => $reward->event_id,
			'name' => $reward->event->name,
			'bonus_xp' => $reward->bonus_xp,
			'accessory' => $reward->accessory === null ? null : ['id' => $reward->accessory->code, 'label' => $reward->accessory->label],
		])->all();
	}

	private function rewardMessage(Passage $passage, string $pseudo): string
	{
		$rewards = $this->eventRewards($passage);
		$message = "Bravo {$pseudo} ! +{$passage->reward_xp} XP";

		if ($rewards->isNotEmpty()) {
			$message .= ', bonus '.Arr::join($rewards->map(fn (EventPassage $reward) => $reward->event->name)->all(), ', ', ' et ');
		}

		$accessories = collect([$passage->accessory, ...$rewards->pluck('accessory')])->filter()->pluck('label')->all();

		return match (count($accessories)) {
			0 => $message,
			1 => "{$message}, nouvel accessoire : {$accessories[0]}",
			default => "{$message}, nouveaux accessoires : ".Arr::join($accessories, ', ', ' et '),
		};
	}

	private function eventRewards(Passage $passage): Collection
	{
		return $passage->loadMissing(['eventPassages' => fn ($query) => $query->with('event', 'accessory')->orderBy('id')])->eventPassages;
	}
}
