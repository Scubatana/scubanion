<?php

namespace Tests\Feature\Bornes;

use App\Enums\TerminalStatus;
use App\Models\Terminal;
use App\Services\BorneListener;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use PhpMqtt\Client\Contracts\MqttClient;
use Tests\TestCase;

class BorneListenerTest extends TestCase
{
	use RefreshDatabase;

	private MockInterface $client;

	private ?Closure $onMessage = null;

	private array $subscriptions = [];

	protected function setUp(): void
	{
		parent::setUp();

		Terminal::factory()->create(['code' => 'chateau-01']);
		$this->client = Mockery::mock(MqttClient::class);
		$this->client->shouldReceive('registerMessageReceivedEventHandler')->once()->andReturnUsing(function (Closure $handler): MqttClient {
			$this->onMessage = $handler;

			return $this->client;
		});
		$this->client->shouldReceive('subscribe')->once()->andReturnUsing(function (string $topic, ?callable $callback, int $qualityOfService): void {
			$this->subscriptions[$topic] = [$callback, $qualityOfService];
		});

		app(BorneListener::class)->listen($this->client);
	}

	private function receive(string $topic, array|string $message): void
	{
		($this->onMessage)($this->client, $topic, is_array($message) ? json_encode($message) : $message, 1, false);
	}

	public function test_it_only_listens_to_the_heartbeats_of_every_terminal(): void
	{
		$this->assertSame(['scubanion/bornes/+/etat' => [null, 1]], $this->subscriptions);
	}

	public function test_it_ignores_an_unknown_terminal_and_an_unrelated_topic(): void
	{
		$this->receive('scubanion/bornes/inconnue/etat', ['status' => 'online']);
		$this->receive('scubanion/bornes/chateau-01/passages', ['token' => 'x']);
		$this->receive('autre/chose', 'rien');

		$this->assertSame(TerminalStatus::Offline, Terminal::sole()->status);
	}

	public function test_a_heartbeat_updates_its_terminal(): void
	{
		$this->receive('scubanion/bornes/chateau-01/etat', ['status' => 'online']);

		$this->assertSame(TerminalStatus::Online, Terminal::sole()->status);
	}
}
