<?php

namespace App\Services;

use App\Models\Terminal;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpMqtt\Client\MqttClient;
use Throwable;

class TerminalDisplay
{
	public function __construct(private MqttConnector $connector) {}

	public function show(Terminal $terminal, array $display): void
	{
		try {
			$client = $this->connector->connect('scubanion-affichage-'.Str::random(8), keepSession: false);
			$client->publish(config('bornes.topic_prefix')."/{$terminal->code}/affichage", json_encode($display, JSON_UNESCAPED_UNICODE), MqttClient::QOS_AT_LEAST_ONCE);
			$client->disconnect();
		} catch (Throwable $error) {
			Log::warning("Affichage non envoyé à la borne {$terminal->code}", ['error' => $error->getMessage()]);
		}
	}
}
