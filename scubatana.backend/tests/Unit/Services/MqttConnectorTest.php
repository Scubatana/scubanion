<?php

namespace Tests\Unit\Services;

use App\Services\BorneListener;
use App\Services\MqttConnector;
use Mockery;
use PhpMqtt\Client\Contracts\MqttClient;
use Tests\TestCase;

class MqttConnectorTest extends TestCase
{
	public function test_listen_command_connects_loops_and_disconnects_cleanly(): void
	{
		$client = Mockery::mock(MqttClient::class);
		$client->shouldReceive('loop')->once()->with(true);
		$client->shouldReceive('disconnect')->once();

		$connector = Mockery::mock(MqttConnector::class);
		$connector->shouldReceive('connect')->once()->with('scubanion-api')->andReturn($client);

		$listener = Mockery::mock(BorneListener::class);
		$listener->shouldReceive('listen')->once()->with($client);

		$this->app->instance(MqttConnector::class, $connector);
		$this->app->instance(BorneListener::class, $listener);

		$this->artisan('bornes:ecouter')->assertSuccessful();
	}
}
