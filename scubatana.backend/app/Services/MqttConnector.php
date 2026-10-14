<?php

namespace App\Services;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient as MqttClientContract;
use PhpMqtt\Client\MqttClient;

class MqttConnector
{
	public function connect(string $clientId, bool $keepSession = true): MqttClientContract
	{
		$client = new MqttClient(config('bornes.mqtt.host'), config('bornes.mqtt.port'), $clientId);

		$settings = (new ConnectionSettings)
			->setUsername(config('bornes.mqtt.username'))
			->setPassword(config('bornes.mqtt.password'))
			->setConnectTimeout(10)
			->setKeepAliveInterval(30);

		$client->connect($settings, ! $keepSession);

		return $client;
	}
}
