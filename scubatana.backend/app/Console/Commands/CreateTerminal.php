<?php

namespace App\Console\Commands;

use App\Models\Place;
use App\Models\Terminal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateTerminal extends Command
{
	protected $signature = 'bornes:creer
		{code : identifiant de la borne dans les topics MQTT (minuscules, chiffres et tirets)}
		{nom : nom affiché}
		{--lieu= : identifiant du lieu où la borne est installée}';

	protected $description = 'Déclare une nouvelle borne : seules les bornes déclarées sont écoutées';

	public function handle(): int
	{
		$input = ['code' => $this->argument('code'), 'name' => $this->argument('nom'), 'place_id' => $this->option('lieu')];

		$validator = Validator::make($input, [
			'code' => ['required', 'regex:/^[a-z0-9-]{3,32}$/', 'unique:terminals,code'],
			'name' => ['required', 'string', 'max:80'],
			'place_id' => ['nullable', 'integer', 'exists:'.Place::class.',id'],
		]);

		if ($validator->fails()) {
			foreach ($validator->errors()->all() as $error) {
				$this->error($error);
			}

			return self::FAILURE;
		}

		$terminal = Terminal::create($validator->validated());
		$this->info("Borne {$terminal->code} créée. Topics : ".config('bornes.topic_prefix')."/{$terminal->code}/…");

		return self::SUCCESS;
	}
}
