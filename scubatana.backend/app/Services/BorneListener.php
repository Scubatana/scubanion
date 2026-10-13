<?php

namespace App\Services;

use Illuminate\Support\Str;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\MqttClient as Mqtt;

class BorneListener
{
	public function __construct(private BorneMessageHandler $handler) {}

	public function listen(MqttClient $client): void
	{
		$prefix = config('bornes.topic_prefix');

		$client->registerMessageReceivedEventHandler(function (MqttClient $client, string $topic, string $message): void {
			$this->dispatch($client, $topic, $message);
		});

		$client->subscribe("{$prefix}/+/passages", null, Mqtt::QOS_AT_LEAST_ONCE);
		$client->subscribe("{$prefix}/+/etat", null, Mqtt::QOS_AT_LEAST_ONCE);
	}

	private function dispatch(MqttClient $client, string $topic, string $message): void
	{
		$prefix = config('bornes.topic_prefix');

		if (! Str::startsWith($topic, $prefix.'/')) {
			return;
		}

		[$code, $channel] = array_pad(explode('/', Str::after($topic, $prefix.'/'), 2), 2, null);

		if ($channel === 'passages') {
			$display = $this->handler->handlePassage($code, $message);

			if ($display !== null) {
				$client->publish("{$prefix}/{$code}/affichage", json_encode($display, JSON_UNESCAPED_UNICODE), Mqtt::QOS_AT_LEAST_ONCE);
			}
		} elseif ($channel === 'etat') {
			$this->handler->handleState($code, $message);
		}
	}
}
