<?php

namespace App\Console\Commands;

use App\Services\BorneListener;
use App\Services\MqttConnector;
use Illuminate\Console\Command;

class ListenToTerminals extends Command
{
	protected $signature = 'bornes:ecouter';

	protected $description = 'Écoute les bornes sur le broker MQTT : passages, signaux de vie, et réponses à afficher';

	public function handle(MqttConnector $connector, BorneListener $listener): int
	{
		$client = $connector->connect(config('bornes.mqtt.client_id'));
		$listener->listen($client);

		$this->info('À l\'écoute des bornes sur '.config('bornes.mqtt.host').':'.config('bornes.mqtt.port').'.');

		$client->loop(true);
		$client->disconnect();

		return self::SUCCESS;
	}
}
