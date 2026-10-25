<?php

namespace App\Services;

use App\Models\Passage;
use App\Models\Place;
use App\Models\Terminal;
use App\Models\User;
use App\Models\UserPosition;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PassageService
{
	public function __construct(
		private TerminalTokenService $tokens,
		private CompanionProgressService $progress,
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

		try {
			$passage = DB::transaction(function () use ($player, $terminal, $scannedAt, $tokenHash, $position): Passage {
				User::whereKey($player->id)->lockForUpdate()->first();

				if (Passage::where('token_hash', $tokenHash)->exists()) {
					throw new ConflictHttpException('Ce code de borne a déjà été utilisé.');
				}

				$this->ensurePresent($player, $terminal, $scannedAt, $position);

				$alreadyRewarded = $terminal->passages()
					->where('user_id', $player->id)
					->where('reward_xp', '>', 0)
					->whereBetween('scanned_at', [$scannedAt->startOfDay(), $scannedAt->endOfDay()])
					->exists();

				return $terminal->passages()->create([
					'user_id' => $player->id,
					'token_hash' => $tokenHash,
					'scanned_at' => $scannedAt,
					'reward_xp' => $alreadyRewarded ? 0 : config('bornes.reward_xp'),
				]);
			});
		} catch (UniqueConstraintViolationException) {
			throw new ConflictHttpException('Ce code de borne a déjà été utilisé.');
		}

		if ($passage->isRewarded()) {
			$this->progress->refresh($player);
		}

		return $passage;
	}

	private function ensurePresent(User $player, Terminal $terminal, CarbonImmutable $scannedAt, ?array $position): void
	{
		$place = $terminal->place;

		if ($place === null) {
			return;
		}

		if ($position !== null) {
			if ($position['mocked'] || ! $place->contains($position['latitude'], $position['longitude'], $position['accuracy'])) {
				throw ValidationException::withMessages(['position' => "Tu n'es pas à la borne : rapproche-toi et scanne-la à nouveau."]);
			}

			return;
		}

		$last = $player->position()->first();

		if ($last !== null && ! $this->couldHaveReached($place, $last, $scannedAt)) {
			throw ValidationException::withMessages(['position' => 'Ta dernière position est trop loin de la borne : active ta localisation et scanne-la à nouveau.']);
		}
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

		return [
			'scanned_at' => $passage->scanned_at->toIso8601String(),
			'status' => $passage->isRewarded() ? 'rewarded' : 'already_rewarded',
			'message' => $passage->isRewarded() ? "Bravo {$pseudo} ! +{$passage->reward_xp} XP" : "Déjà passé aujourd'hui, {$pseudo}. Reviens demain !",
			'reward_xp' => $passage->reward_xp,
		];
	}
}
