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
			$this->dispatch($topic, $message);
		});

		$client->subscribe("{$prefix}/+/etat", null, Mqtt::QOS_AT_LEAST_ONCE);
	}

	private function dispatch(string $topic, string $message): void
	{
		$prefix = config('bornes.topic_prefix');

		if (! Str::startsWith($topic, $prefix.'/')) {
			return;
		}

		[$code, $channel] = array_pad(explode('/', Str::after($topic, $prefix.'/'), 2), 2, null);

		if ($channel === 'etat') {
			$this->handler->handleState($code, $message);
		}
	}
}
