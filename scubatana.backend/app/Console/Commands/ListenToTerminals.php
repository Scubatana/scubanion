<?php

namespace App\Console\Commands;

use App\Services\BorneListener;
use App\Services\MqttConnector;
use Illuminate\Console\Command;

class ListenToTerminals extends Command
{
	protected $signature = 'bornes:ecouter';

	protected $description = 'Écoute les signaux de vie des bornes sur le broker MQTT';

	public function handle(MqttConnector $connector, BorneListener $listener): int
	{
		$client = $connector->connect(config('bornes.mqtt.client_id'));
		$listener->listen($client);

		if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
			pcntl_async_signals(true);
			$stop = fn () => $client->interrupt();
			pcntl_signal(SIGTERM, $stop);
			pcntl_signal(SIGINT, $stop);
		}

		$this->info('À l\'écoute des bornes sur '.config('bornes.mqtt.host').':'.config('bornes.mqtt.port').'.');

		$client->loop(true);

		if (function_exists('pcntl_signal')) {
			pcntl_signal(SIGTERM, SIG_DFL);
			pcntl_signal(SIGINT, SIG_DFL);
		}

		$client->disconnect();

		return self::SUCCESS;
	}
}
