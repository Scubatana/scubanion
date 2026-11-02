<?php

namespace App\Console\Commands;

use App\Services\MqttAccounts;
use Illuminate\Console\Command;
use RuntimeException;

class WriteMqttAccounts extends Command
{
	protected $signature = 'bornes:comptes-mqtt';

	protected $description = 'Écrit les comptes et les droits du broker MQTT : un compte pour l\'API, un par borne';

	public function handle(MqttAccounts $accounts): int
	{
		try {
			$directory = $accounts->write();
		} catch (RuntimeException $error) {
			$this->error($error->getMessage());

			return self::FAILURE;
		}

		$this->info("Comptes MQTT écrits dans {$directory}.");
		$this->line('Pour que le broker les relise : docker compose kill -s HUP mosquitto');

		return self::SUCCESS;
	}
}
