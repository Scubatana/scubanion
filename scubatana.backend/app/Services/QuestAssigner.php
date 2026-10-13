<?php

namespace App\Services;

use App\Enums\QuestSource;
use App\Models\Quest;
use App\Models\User;
use Carbon\CarbonInterface;

class QuestAssigner
{
	public function __construct(private QuestEngine $engine) {}

	public function assign(User $player, CarbonInterface $now): void
	{
		$position = $player->position()->first();

		$quests = Quest::availableAt($now)
			->where('auto_assign', true)
			->whereHas('tasks')
			->with('targetZone.points')
			->get();

		foreach ($quests as $quest) {
			if ($quest->target_ambition !== null && $quest->target_ambition !== $player->ambition) {
				continue;
			}

			if ($quest->target_zone_id !== null && ($position === null || ! $quest->targetZone->contains($position->latitude, $position->longitude))) {
				continue;
			}

			$this->engine->give($player, $quest, QuestSource::Rule, $now);
		}
	}
}
