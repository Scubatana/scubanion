<?php

namespace App\Console\Commands;

use App\Models\Place;
use App\Models\Terminal;
use App\Services\MqttAccounts;
use App\Services\TerminalTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

class CreateTerminal extends Command
{
	protected $signature = 'bornes:creer
		{code : identifiant de la borne dans les topics MQTT (3 à 16 minuscules, chiffres et tirets)}
		{nom : nom affiché}
		{--lieu= : identifiant du lieu où la borne est installée}';

	protected $description = 'Déclare une nouvelle borne : seules les bornes déclarées sont écoutées';

	public function handle(MqttAccounts $accounts): int
	{
		$input = ['code' => $this->argument('code'), 'name' => $this->argument('nom'), 'place_id' => $this->option('lieu')];

		$validator = Validator::make($input, [
			'code' => ['required', 'regex:/^[a-z0-9-]{3,16}$/', 'unique:terminals,code', Rule::notIn([config('bornes.mqtt.username')])],
			'name' => ['required', 'string', 'max:80'],
			'place_id' => ['nullable', 'integer', 'exists:'.Place::class.',id'],
		]);

		if ($validator->fails()) {
			foreach ($validator->errors()->all() as $error) {
				$this->error($error);
			}

			return self::FAILURE;
		}

		$secret = TerminalTokenService::newSecret();
		$terminal = Terminal::create([...$validator->validated(), 'secret' => $secret]);
		$this->info("Borne {$terminal->code} créée. Topics : ".config('bornes.topic_prefix')."/{$terminal->code}/…");
		$this->line("Secret à enregistrer dans la borne (il ne sera plus affiché) : {$secret}");

		try {
			$accounts->write();
			$this->line('Compte MQTT de la borne ajouté. Pour que le broker le relise : docker compose kill -s HUP mosquitto');
		} catch (RuntimeException $error) {
			$this->warn("Compte MQTT non écrit : {$error->getMessage()}");
		}

		return self::SUCCESS;
	}
}
