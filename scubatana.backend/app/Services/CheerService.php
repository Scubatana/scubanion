<?php

namespace App\Services;

use App\Models\Accessory;
use App\Models\Cheer;
use App\Models\UserAccessory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CheerService
{
	// Chaque coup de patte reçu dans la journée remonte l'humeur du compagnon, dans la limite d'un plafond par jour.
	public const MOOD_BONUS = 5;

	public const MOOD_BONUS_MAX = 15;

	public const STREAK_MILESTONES = [
		7 => 'paw_scarf',
		30 => 'paw_bucket_hat',
		100 => 'starfish_glasses',
	];

	// Faux si un coup de patte a déjà été envoyé à cet ami aujourd'hui.
	public function send(User $player, User $friend): bool
	{
		try {
			Cheer::create(['user_id' => $player->id, 'friend_id' => $friend->id, 'day' => $this->today()]);
		} catch (UniqueConstraintViolationException) {
			return false;
		}

		return true;
	}

	// Ceux à qui le joueur a envoyé un coup de patte aujourd'hui, et ceux qui lui en ont envoyé un.
	public function todayFor(User $player): array
	{
		$cheers = Cheer::where('day', $this->today())
			->where(fn ($query) => $query->where('user_id', $player->id)->orWhere('friend_id', $player->id))
			->get();

		return [
			'sent' => $cheers->where('user_id', $player->id)->pluck('friend_id')->all(),
			'received' => $cheers->where('friend_id', $player->id)->pluck('user_id')->all(),
		];
	}

	public function moodBonus(User $user, CarbonImmutable $day): int
	{
		$received = Cheer::where('friend_id', $user->id)->where('day', $day->toDateString())->count();

		return min(self::MOOD_BONUS_MAX, $received * self::MOOD_BONUS);
	}

	public function exchangeDaysFor(User $player, ?User $friend = null): array
	{
		return DB::table('cheers as sent')
			->join('cheers as back', function ($join) {
				$join->on('back.user_id', '=', 'sent.friend_id')
					->on('back.friend_id', '=', 'sent.user_id')
					->on('back.day', '=', 'sent.day');
			})
			->where('sent.user_id', $player->id)
			->when($friend !== null, fn ($query) => $query->where('sent.friend_id', $friend->id))
			->orderBy('sent.day')
			->get(['sent.friend_id', 'sent.day'])
			->groupBy('friend_id')
			->map(fn ($rows) => $rows->map(fn ($row) => substr($row->day, 0, 10))->all())
			->all();
	}

	public function streak(array $exchangeDays, CarbonImmutable $since): array
	{
		$exchanged = array_flip(array_filter($exchangeDays, fn (string $day) => $day >= $since->toDateString()));

		if ($exchanged === []) {
			return ['days' => 0, 'record' => 0];
		}

		$today = CarbonImmutable::now()->startOfDay();
		$last = isset($exchanged[$today->toDateString()]) ? $today : $today->subDay();
		$days = 0;
		$record = 0;
		$jokerWeek = null;

		for ($day = CarbonImmutable::parse(array_key_first($exchanged)); $day->lte($last); $day = $day->addDay()) {
			if (isset($exchanged[$day->toDateString()])) {
				$record = max($record, ++$days);
			} elseif ($days > 0 && $jokerWeek !== $day->format('o-W')) {
				$jokerWeek = $day->format('o-W');
			} else {
				$days = 0;
			}
		}

		return ['days' => $days, 'record' => $record];
	}

	public function rewardMilestones(User $player, User $friend, CarbonImmutable $since): array
	{
		$days = $this->streak($this->exchangeDaysFor($player, $friend)[$friend->id] ?? [], $since)['days'];
		$codes = array_values(array_filter(self::STREAK_MILESTONES, fn (int $milestone) => $days >= $milestone, ARRAY_FILTER_USE_KEY));

		if ($codes === []) {
			return [];
		}

		$ids = Accessory::active()->whereIn('code', $codes)->pluck('id', 'code');
		$unlocked = [];

		foreach ($codes as $code) {
			$id = $ids[$code] ?? null;

			if ($id === null) {
				continue;
			}

			UserAccessory::insertOrIgnore(['user_id' => $friend->id, 'accessory_id' => $id, 'unlocked_at' => now()]);

			if (UserAccessory::insertOrIgnore(['user_id' => $player->id, 'accessory_id' => $id, 'unlocked_at' => now()]) > 0) {
				$unlocked[] = $code;
			}
		}

		return $unlocked;
	}

	private function today(): string
	{
		return CarbonImmutable::now()->toDateString();
	}
}
