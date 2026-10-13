<?php

namespace Tests\Feature\Bornes;

use App\Enums\TerminalStatus;
use App\Models\Terminal;
use App\Models\User;
use App\Services\BadgeTokenService;
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
		$this->client->shouldReceive('subscribe')->twice()->andReturnUsing(function (string $topic, ?callable $callback, int $qualityOfService): void {
			$this->subscriptions[$topic] = [$callback, $qualityOfService];
		});

		app(BorneListener::class)->listen($this->client);
	}

	private function receive(string $topic, array|string $message): void
	{
		($this->onMessage)($this->client, $topic, is_array($message) ? json_encode($message) : $message, 1, false);
	}

	public function test_it_subscribes_to_the_passages_and_the_heartbeats_of_every_terminal(): void
	{
		$this->assertSame([
			'scubanion/bornes/+/passages' => [null, 1],
			'scubanion/bornes/+/etat' => [null, 1],
		], $this->subscriptions);
	}

	public function test_a_passage_is_answered_on_the_display_topic_of_its_terminal(): void
	{
		$token = app(BadgeTokenService::class)->issue(User::factory()->create(['pseudo' => 'Lea']))['token'];

		$this->client->shouldReceive('publish')->once()->with(
			'scubanion/bornes/chateau-01/affichage',
			json_encode(['passage' => 'p-1', 'status' => 'rewarded', 'message' => 'Bravo Lea ! +100 XP', 'reward_xp' => 100], JSON_UNESCAPED_UNICODE),
			1,
		);

		$this->receive('scubanion/bornes/chateau-01/passages', ['id' => 'p-1', 'token' => $token, 'scanned_at' => now()->toIso8601String()]);
	}

	public function test_nothing_is_published_for_an_unknown_terminal_or_an_unrelated_topic(): void
	{
		$this->client->shouldNotReceive('publish');

		$this->receive('scubanion/bornes/inconnue/passages', ['id' => 'p-1', 'token' => 'x', 'scanned_at' => now()->toIso8601String()]);
		$this->receive('scubanion/bornes/chateau-01/affichage', ['message' => 'écho']);
		$this->receive('autre/chose', 'rien');
	}

	public function test_a_heartbeat_updates_its_terminal(): void
	{
		$this->receive('scubanion/bornes/chateau-01/etat', ['status' => 'online']);

		$this->assertSame(TerminalStatus::Online, Terminal::sole()->status);
	}
}
