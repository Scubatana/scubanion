<?php

namespace App\Console\Commands;

use App\Models\Encounter;
use Illuminate\Console\Command;

class ForgetEncounterZones extends Command
{
	protected $signature = 'rencontres:oublier-zones';

	protected $description = 'Efface la zone approximative des rencontres plus anciennes que la durée de conservation (30 jours par défaut)';

	public function handle(): int
	{
		$forgotten = Encounter::whereNotNull('zone')
			->where('met_at', '<', now()->subDays(config('encounters.zone_retention_days')))
			->update(['zone' => null]);

		$this->info("{$forgotten} zone(s) de rencontre effacée(s).");

		return self::SUCCESS;
	}
}
