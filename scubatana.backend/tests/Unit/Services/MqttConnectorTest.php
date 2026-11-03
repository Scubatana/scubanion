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
		$heartbeat = tempnam(sys_get_temp_dir(), 'heartbeat_test_');

		$client = Mockery::mock(MqttClient::class);
		$client->shouldReceive('registerLoopEventHandler')->once();
		$client->shouldReceive('loop')->once()->with(true)->andReturnUsing(function () use ($heartbeat) {
			$this->assertFileExists($heartbeat);
			$this->assertGreaterThan(0, (int) file_get_contents($heartbeat));
		});
		$client->shouldReceive('disconnect')->once();

		$connector = Mockery::mock(MqttConnector::class);
		$connector->shouldReceive('connect')->once()->with('scubanion-api')->andReturn($client);

		$listener = Mockery::mock(BorneListener::class);
		$listener->shouldReceive('listen')->once()->with($client);

		$this->app->instance(MqttConnector::class, $connector);
		$this->app->instance(BorneListener::class, $listener);

		$this->artisan('bornes:ecouter', ['--heartbeat' => $heartbeat])->assertSuccessful();

		$this->assertFileDoesNotExist($heartbeat);
	}
}
