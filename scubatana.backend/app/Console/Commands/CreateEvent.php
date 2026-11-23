<?php

namespace App\Console\Commands;

use App\Models\Accessory;
use App\Models\Event;
use App\Models\Terminal;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateEvent extends Command
{
	protected $signature = 'evenements:creer
		{nom : nom affiché dans l’app et sur l’écran de la borne (40 caractères au plus)}
		{debut : début de l’événement, heure de Paris (ex. "2026-10-17 00:00")}
		{fin : fin de l’événement, exclue (ex. "2026-10-19 00:00" pour un week-end)}
		{--bonus= : XP ajoutée au premier passage récompensé du jour}
		{--accessoire= : code d’un accessoire earnable, gagné au premier passage récompensé pendant l’événement}
		{--borne=* : code d’une borne concernée (option à répéter)}
		{--toutes-les-bornes : l’événement vaut pour toutes les bornes, y compris celles créées plus tard}
		{--description= : texte affiché dans l’app}';

	protected $description = 'Crée un événement limité dans le temps : bonus d’XP et/ou accessoire au passage en borne';

	public function handle(): int
	{
		$input = [
			'name' => $this->argument('nom'),
			'starts_at' => $this->argument('debut'),
			'ends_at' => $this->argument('fin'),
			'bonus_xp' => $this->option('bonus'),
			'accessory' => $this->option('accessoire'),
			'terminals' => $this->option('borne') ?: null,
			'all_terminals' => $this->option('toutes-les-bornes') ?: null,
			'description' => $this->option('description'),
		];

		$validator = Validator::make($input, [
			'name' => ['required', 'string', 'max:40'],
			'starts_at' => ['required', 'date'],
			'ends_at' => ['required', 'date', 'after:starts_at'],
			'bonus_xp' => ['nullable', 'integer', 'min:1', 'max:10000', 'required_without:accessory'],
			'accessory' => ['nullable', 'string', Rule::exists(Accessory::class, 'code')->where('earnable', true)->where('active', true)],
			'terminals' => ['nullable', 'array', 'required_without:all_terminals', 'prohibits:all_terminals'],
			'terminals.*' => ['string', 'distinct', Rule::exists(Terminal::class, 'code')],
			'all_terminals' => ['nullable', 'boolean'],
			'description' => ['nullable', 'string', 'max:500'],
		], [
			'bonus_xp.required_without' => 'Un événement doit donner un bonus d’XP (--bonus), un accessoire (--accessoire) ou les deux.',
			'terminals.required_without' => 'Indique les bornes concernées (--borne=code, à répéter) ou --toutes-les-bornes.',
			'terminals.prohibits' => 'Choisis --borne ou --toutes-les-bornes, pas les deux.',
		]);

		if ($validator->fails()) {
			foreach ($validator->errors()->all() as $error) {
				$this->error($error);
			}

			return self::FAILURE;
		}

		$data = $validator->validated();
		$event = DB::transaction(function () use ($data): Event {
			$event = Event::create([
				'name' => $data['name'],
				'description' => $data['description'] ?? null,
				'starts_at' => CarbonImmutable::parse($data['starts_at'], config('app.timezone')),
				'ends_at' => CarbonImmutable::parse($data['ends_at'], config('app.timezone')),
				'bonus_xp' => (int) ($data['bonus_xp'] ?? 0),
				'accessory_id' => isset($data['accessory']) ? Accessory::where('code', $data['accessory'])->value('id') : null,
				'all_terminals' => isset($data['all_terminals']),
			]);
			$event->terminals()->attach(Terminal::whereIn('code', $data['terminals'] ?? [])->pluck('id'));

			return $event;
		});

		$where = $event->all_terminals ? 'toutes les bornes' : implode(', ', $data['terminals']);
		$this->info("Événement « {$event->name} » créé (n° {$event->id}), du {$event->starts_at->format('d/m/Y H:i')} au {$event->ends_at->format('d/m/Y H:i')} : {$where}.");

		return self::SUCCESS;
	}
}
