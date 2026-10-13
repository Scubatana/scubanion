<?php

namespace App\Services;

use App\Models\Companion;
use App\Models\User;

class CompanionService
{
	public const DEFAULT_NAME = 'Scubanion';

	public const DEFAULT_SPECIES = 'scuba';

	public const DEFAULT_COLOR = 'ocean';

	public const XP_PER_LEVEL = 1000;

	public function createFor(User $user): Companion
	{
		return Companion::firstOrCreate(
			['user_id' => $user->id],
			[
				'name' => self::DEFAULT_NAME,
				'species' => self::DEFAULT_SPECIES,
				'color' => self::DEFAULT_COLOR,
				'accessories' => [],
				'level' => 1,
				'xp' => 0,
				'mood' => 100,
				'health' => 100,
			],
		);
	}

	public function customize(Companion $companion, array $appearance): Companion
	{
		$companion->update([
			'name' => $appearance['name'],
			'species' => $appearance['species'],
			'color' => $appearance['color'],
			'accessories' => array_values($appearance['accessories']),
		]);

		return $companion;
	}

	public function xpForNextLevel(int $level): int
	{
		return $level * self::XP_PER_LEVEL;
	}
}
