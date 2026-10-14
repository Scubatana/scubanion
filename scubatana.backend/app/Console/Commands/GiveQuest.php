<?php

namespace App\Console\Commands;

use App\Enums\QuestSource;
use App\Models\Quest;
use App\Models\User;
use App\Services\QuestEngine;
use Illuminate\Console\Command;

class GiveQuest extends Command
{
	protected $signature = 'quetes:donner
		{quete : identifiant de la quête}
		{joueur : identifiant du joueur}';

	protected $description = 'Donne une quête à un joueur, sans passer par les règles d\'attribution (attribution nominative)';

	public function handle(QuestEngine $engine): int
	{
		$quest = Quest::availableAt(now())->whereHas('tasks')->find($this->argument('quete'));
		$player = User::find($this->argument('joueur'));

		if ($quest === null || $player === null) {
			$this->error('Quête publiée en cours ou joueur introuvable.');

			return self::FAILURE;
		}

		$userQuest = $engine->give($player, $quest, QuestSource::Manual, now());
		$this->info("« {$quest->name} » donnée à {$player->pseudo} pour la période {$userQuest->period}.");

		return self::SUCCESS;
	}
}
