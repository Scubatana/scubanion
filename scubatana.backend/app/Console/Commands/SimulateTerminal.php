<?php

namespace App\Console\Commands;

use App\Models\Terminal;
use App\Services\MqttConnector;
use App\Services\TerminalTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\MqttClient as Mqtt;

class SimulateTerminal extends Command
{
	private const DISPLAY_TIMEOUT_SECONDS = 60;

	protected $signature = 'bornes:simuler
		{borne : code de la borne}
		{--etat= : envoie un signal de vie (online ou offline) au lieu de simuler un scan}';

	protected $description = 'Joue le rôle d\'une borne : donne le jeton qu\'elle transmettrait au téléphone, puis affiche la réponse du serveur, ou envoie un signal de vie';

	public function handle(MqttConnector $connector, TerminalTokenService $tokens): int
	{
		$terminal = Terminal::where('code', $this->argument('borne'))->first();

		if ($terminal === null) {
			$this->error('Borne inconnue : déclare-la avec php artisan bornes:creer.');

			return self::FAILURE;
		}

		$topic = config('bornes.topic_prefix')."/{$terminal->code}";
		$client = $connector->connect('simulateur-'.$terminal->code.'-'.Str::random(6), keepSession: false);

		if ($this->option('etat') !== null) {
			$client->publish("{$topic}/etat", json_encode(['status' => $this->option('etat'), 'firmware' => 'simulateur']), Mqtt::QOS_AT_LEAST_ONCE, true);
			$client->disconnect();
			$this->info("Signal « {$this->option('etat')} » envoyé pour la borne {$terminal->code}.");

			return self::SUCCESS;
		}

		$scannedAt = now();
		$this->line('Jeton transmis au téléphone : '.$tokens->generate($terminal, $scannedAt));
		$this->line('Envoie-le avec POST /api/me/passages ; j\'attends la réponse du serveur…');

		$display = $this->waitForDisplay($client, "{$topic}/affichage", $scannedAt->toIso8601String());
		$client->disconnect();

		if ($display === null) {
			$this->error('Pas de réponse du serveur dans la minute.');

			return self::FAILURE;
		}

		$this->line("Écran de la borne : {$display['message']}");

		return self::SUCCESS;
	}

	private function waitForDisplay(MqttClient $client, string $topic, string $scannedAt): ?array
	{
		$display = null;

		$client->registerMessageReceivedEventHandler(function (MqttClient $client, string $replyTopic, string $message) use ($topic, $scannedAt, &$display): void {
			$data = json_decode($message, true);

			if ($replyTopic === $topic && ($data['scanned_at'] ?? null) === $scannedAt) {
				$display = $data;
				$client->interrupt();
			}
		});

		$client->registerLoopEventHandler(function (MqttClient $client, float $elapsedTime): void {
			if ($elapsedTime > self::DISPLAY_TIMEOUT_SECONDS) {
				$client->interrupt();
			}
		});

		$client->subscribe($topic, null, Mqtt::QOS_AT_LEAST_ONCE);
		$client->loop(true);

		return $display;
	}
}
