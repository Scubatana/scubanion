<?php

namespace Tests\Unit\Services;

use App\Models\Terminal;
use App\Services\MqttConnector;
use App\Services\TerminalDisplay;
use Illuminate\Support\Facades\Log;
use Mockery;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\MqttClient as Client;
use RuntimeException;
use Tests\TestCase;

class TerminalDisplayTest extends TestCase
{
	private const DISPLAY = [
		'scanned_at' => '2026-10-13T15:00:00+02:00',
		'status' => 'rewarded',
		'message' => 'Bravo Léa ! +100 XP',
		'reward_xp' => 100,
	];

	public function test_it_publishes_the_display_on_the_terminal_topic_then_disconnects(): void
	{
		$client = Mockery::mock(MqttClient::class);
		$client->shouldReceive('publish')
			->once()
			->with('scubanion/bornes/chateau-01/affichage', '{"scanned_at":"2026-10-13T15:00:00+02:00","status":"rewarded","message":"Bravo Léa ! +100 XP","reward_xp":100}', Client::QOS_AT_LEAST_ONCE)
			->ordered();
		$client->shouldReceive('disconnect')->once()->ordered();

		$connector = Mockery::mock(MqttConnector::class);
		$connector->shouldReceive('connect')
			->once()
			->with(Mockery::pattern('/^scubanion-affichage-[A-Za-z0-9]{8}$/'), false)
			->andReturn($client);

		(new TerminalDisplay($connector))->show(new Terminal(['code' => 'chateau-01']), self::DISPLAY);
	}

	public function test_each_display_uses_its_own_client_id_so_two_passages_never_kick_each_other(): void
	{
		$clientIds = [];
		$client = Mockery::mock(MqttClient::class);
		$client->shouldReceive('publish')->twice();
		$client->shouldReceive('disconnect')->twice();

		$connector = Mockery::mock(MqttConnector::class);
		$connector->shouldReceive('connect')->twice()->andReturnUsing(function (string $clientId) use (&$clientIds, $client): MqttClient {
			$clientIds[] = $clientId;

			return $client;
		});

		$display = new TerminalDisplay($connector);
		$display->show(new Terminal(['code' => 'chateau-01']), self::DISPLAY);
		$display->show(new Terminal(['code' => 'chateau-01']), self::DISPLAY);

		$this->assertCount(2, array_unique($clientIds));
	}

	public function test_an_unreachable_broker_is_logged_and_never_breaks_the_passage(): void
	{
		Log::spy();

		$connector = Mockery::mock(MqttConnector::class);
		$connector->shouldReceive('connect')->once()->andThrow(new RuntimeException('Connection refused'));

		(new TerminalDisplay($connector))->show(new Terminal(['code' => 'chateau-01']), self::DISPLAY);

		Log::shouldHaveReceived('warning')->once()->with('Affichage non envoyé à la borne chateau-01', ['error' => 'Connection refused']);
	}

	public function test_a_failed_publish_is_logged_without_throwing(): void
	{
		Log::spy();

		$client = Mockery::mock(MqttClient::class);
		$client->shouldReceive('publish')->once()->andThrow(new RuntimeException('Broken pipe'));
		$client->shouldNotReceive('disconnect');

		$connector = Mockery::mock(MqttConnector::class);
		$connector->shouldReceive('connect')->once()->andReturn($client);

		(new TerminalDisplay($connector))->show(new Terminal(['code' => 'port-02']), self::DISPLAY);

		Log::shouldHaveReceived('warning')->once()->with('Affichage non envoyé à la borne port-02', ['error' => 'Broken pipe']);
	}
}
