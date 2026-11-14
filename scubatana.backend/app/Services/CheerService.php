<?php

namespace App\Services;

use App\Models\Cheer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

class CheerService
{
	// Chaque coup de patte reçu dans la journée remonte l'humeur du compagnon, dans la limite d'un plafond par jour.
	public const MOOD_BONUS = 5;

	public const MOOD_BONUS_MAX = 15;

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
		$cheers = Cheer::whereDate('day', $this->today())
			->where(fn ($query) => $query->where('user_id', $player->id)->orWhere('friend_id', $player->id))
			->get();

		return [
			'sent' => $cheers->where('user_id', $player->id)->pluck('friend_id')->all(),
			'received' => $cheers->where('friend_id', $player->id)->pluck('user_id')->all(),
		];
	}

	public function moodBonus(User $user, CarbonImmutable $day): int
	{
		$received = Cheer::where('friend_id', $user->id)->whereDate('day', $day->toDateString())->count();

		return min(self::MOOD_BONUS_MAX, $received * self::MOOD_BONUS);
	}

	private function today(): string
	{
		return CarbonImmutable::now()->toDateString();
	}
}
