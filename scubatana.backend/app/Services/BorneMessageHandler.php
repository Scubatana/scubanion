<?php

namespace App\Services;

use App\Enums\TerminalStatus;
use App\Models\Passage;
use App\Models\Terminal;
use Carbon\CarbonImmutable;
use Throwable;

class BorneMessageHandler
{
	private const FUTURE_TOLERANCE_MINUTES = 5;

	public function __construct(private BadgeTokenService $badges) {}

	public function handlePassage(string $terminalCode, string $message): ?array
	{
		$terminal = Terminal::where('code', $terminalCode)->first();

		if ($terminal === null) {
			return null;
		}

		$data = json_decode($message, true);
		$clientId = is_array($data) && is_string($data['id'] ?? null) && $data['id'] !== '' && strlen($data['id']) <= 64 ? $data['id'] : null;
		$scannedAt = $this->scannedAt($data);

		if ($clientId === null || $scannedAt === null || ! is_string($data['token'] ?? null)) {
			return $this->display($clientId, 'invalid_message', 'Passage illisible, réessaie.', 0);
		}

		$terminal->update(['status' => TerminalStatus::Online, 'last_seen_at' => now()]);

		$existing = $terminal->passages()->where('client_id', $clientId)->first();

		if ($existing !== null) {
			return $this->resultFor($existing);
		}

		$player = $this->badges->verify($data['token'], $scannedAt);

		if ($player === null) {
			return $this->display($clientId, 'invalid_token', 'Badge non reconnu.', 0);
		}

		$alreadyRewarded = $terminal->passages()
			->where('user_id', $player->id)
			->where('reward_xp', '>', 0)
			->whereBetween('scanned_at', [$scannedAt->startOfDay(), $scannedAt->endOfDay()])
			->exists();

		$passage = $terminal->passages()->create([
			'user_id' => $player->id,
			'client_id' => $clientId,
			'scanned_at' => $scannedAt,
			'reward_xp' => $alreadyRewarded ? 0 : config('bornes.reward_xp'),
		]);

		return $this->resultFor($passage);
	}

	public function handleState(string $terminalCode, string $message): void
	{
		$terminal = Terminal::where('code', $terminalCode)->first();
		$data = json_decode($message, true);
		$status = is_array($data) && is_string($data['status'] ?? null) ? TerminalStatus::tryFrom($data['status']) : null;

		if ($terminal === null || $status === null) {
			return;
		}

		$terminal->update([
			'status' => $status,
			'last_seen_at' => $status === TerminalStatus::Online ? now() : $terminal->last_seen_at,
			'firmware' => is_string($data['firmware'] ?? null) ? substr($data['firmware'], 0, 32) : $terminal->firmware,
		]);
	}

	private function scannedAt(mixed $data): ?CarbonImmutable
	{
		if (! is_array($data) || ! is_string($data['scanned_at'] ?? null)) {
			return null;
		}

		try {
			$scannedAt = CarbonImmutable::parse($data['scanned_at'])->setTimezone(config('app.timezone'));
		} catch (Throwable) {
			return null;
		}

		$tooOld = $scannedAt->lt(now()->subHours(config('bornes.max_replay_hours')));
		$inTheFuture = $scannedAt->gt(now()->addMinutes(self::FUTURE_TOLERANCE_MINUTES));

		return $tooOld || $inTheFuture ? null : $scannedAt;
	}

	private function resultFor(Passage $passage): array
	{
		$pseudo = $passage->user->pseudo ?? 'toi';

		return $passage->isRewarded()
			? $this->display($passage->client_id, 'rewarded', "Bravo {$pseudo} ! +{$passage->reward_xp} XP", $passage->reward_xp)
			: $this->display($passage->client_id, 'already_rewarded', "Déjà passé aujourd'hui, {$pseudo}. Reviens demain !", 0);
	}

	private function display(?string $passageId, string $status, string $message, int $rewardXp): array
	{
		return ['passage' => $passageId, 'status' => $status, 'message' => $message, 'reward_xp' => $rewardXp];
	}
}
