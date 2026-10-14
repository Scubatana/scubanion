<?php

namespace App\Services;

use App\Models\QuestBan;
use App\Models\User;
use App\Models\UserPosition;
use App\Support\Geo;
use Carbon\CarbonImmutable;

class PositionService
{
	public function record(User $player, float $latitude, float $longitude, ?int $accuracy, bool $mocked, CarbonImmutable $recordedAt): void
	{
		$previous = $player->position()->first();

		if ($mocked) {
			$this->ban($player, 'mocked', ['position' => [$latitude, $longitude], 'recorded_at' => $recordedAt->toIso8601String()]);
		} elseif ($previous !== null) {
			$this->checkSpeed($player, $previous, $latitude, $longitude, $recordedAt);
		}

		if ($previous !== null && $recordedAt->lt($previous->recorded_at)) {
			return;
		}

		UserPosition::updateOrCreate(
			['user_id' => $player->id],
			['latitude' => $latitude, 'longitude' => $longitude, 'accuracy_m' => $accuracy, 'mocked' => $mocked, 'recorded_at' => $recordedAt],
		);
	}

	private function checkSpeed(User $player, UserPosition $previous, float $latitude, float $longitude, CarbonImmutable $recordedAt): void
	{
		$distance = Geo::distanceInMeters($previous->latitude, $previous->longitude, $latitude, $longitude);

		if ($distance <= config('positions.teleport_min_distance_m')) {
			return;
		}

		$seconds = abs($previous->recorded_at->diffInSeconds($recordedAt, false));
		$speed = $seconds > 0 ? $distance / $seconds * 3.6 : INF;

		if ($speed > config('positions.teleport_speed_kmh')) {
			$this->ban($player, 'teleport', [
				'from' => [$previous->latitude, $previous->longitude, $previous->recorded_at->toIso8601String()],
				'to' => [$latitude, $longitude, $recordedAt->toIso8601String()],
				'distance_m' => (int) round($distance),
				'speed_kmh' => is_finite($speed) ? (int) round($speed) : null,
			]);
		}
	}

	private function ban(User $player, string $reason, array $evidence): void
	{
		$until = now()->addHours(config('positions.ban_hours'));
		$current = $player->questBans()->activeAt(now())->where('reason', $reason)->latest('ends_at')->first();

		if ($current !== null) {
			$current->update(['ends_at' => $until]);

			return;
		}

		QuestBan::create(['user_id' => $player->id, 'reason' => $reason, 'evidence' => $evidence, 'starts_at' => now(), 'ends_at' => $until]);
	}
}
