<?php

namespace App\Console\Commands;

use App\Services\BorneListener;
use App\Services\MqttConnector;
use Illuminate\Console\Command;

class ListenToTerminals extends Command
{
	protected $signature = 'bornes:ecouter {--heartbeat= : Chemin du fichier de heartbeat pour le healthcheck}';

	protected $description = 'Écoute les signaux de vie des bornes sur le broker MQTT';

	public function handle(MqttConnector $connector, BorneListener $listener): int
	{
		$heartbeatPath = $this->option('heartbeat') ?: config('bornes.mqtt.heartbeat_path', '/tmp/bornes-ecouter.heartbeat');

		$client = $connector->connect(config('bornes.mqtt.client_id'));
		$listener->listen($client);

		$this->updateHeartbeat($heartbeatPath);

		if (method_exists($client, 'registerLoopEventHandler')) {
			$lastHeartbeat = microtime(true);
			$client->registerLoopEventHandler(function () use (&$lastHeartbeat, $heartbeatPath) {
				$now = microtime(true);
				if ($now - $lastHeartbeat >= 5.0) {
					$lastHeartbeat = $now;
					$this->updateHeartbeat($heartbeatPath);
				}
			});
		}

		if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
			pcntl_async_signals(true);
			$stop = function () use ($client, $heartbeatPath) {
				$this->removeHeartbeat($heartbeatPath);
				$client->interrupt();
			};
			pcntl_signal(SIGTERM, $stop);
			pcntl_signal(SIGINT, $stop);
		}

		$this->info('À l\'écoute des bornes sur '.config('bornes.mqtt.host').':'.config('bornes.mqtt.port').'.');

		$client->loop(true);

		if (function_exists('pcntl_signal')) {
			pcntl_signal(SIGTERM, SIG_DFL);
			pcntl_signal(SIGINT, SIG_DFL);
		}

		$this->removeHeartbeat($heartbeatPath);
		$client->disconnect();

		return self::SUCCESS;
	}

	private function updateHeartbeat(?string $path): void
	{
		if ($path === null || $path === '') {
			return;
		}

		@file_put_contents($path, (string) time());
	}

	private function removeHeartbeat(?string $path): void
	{
		if ($path === null || $path === '') {
			return;
		}

		if (file_exists($path)) {
			@unlink($path);
		}
	}
}
