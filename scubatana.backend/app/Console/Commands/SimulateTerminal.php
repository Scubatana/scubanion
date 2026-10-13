<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BadgeTokenService;
use App\Services\MqttConnector;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\MqttClient as Mqtt;

class SimulateTerminal extends Command
{
	private const REPLY_TIMEOUT_SECONDS = 10;

	protected $signature = 'bornes:simuler
		{borne : code de la borne}
		{--joueur= : identifiant du joueur qui badge (le jeton est généré pour lui)}
		{--jeton= : jeton de badge à envoyer tel quel}
		{--etat= : envoie un signal de vie (online ou offline) au lieu d\'un passage}';

	protected $description = 'Joue le rôle d\'une borne : envoie un passage (ou un signal de vie) sur le broker et affiche la réponse';

	public function handle(MqttConnector $connector, BadgeTokenService $badges): int
	{
		$code = $this->argument('borne');
		$prefix = config('bornes.topic_prefix');
		$client = $connector->connect('simulateur-'.$code.'-'.Str::random(6), keepSession: false);

		if ($this->option('etat') !== null) {
			$client->publish("{$prefix}/{$code}/etat", json_encode(['status' => $this->option('etat'), 'firmware' => 'simulateur']), Mqtt::QOS_AT_LEAST_ONCE, true);
			$client->disconnect();
			$this->info("Signal « {$this->option('etat')} » envoyé pour la borne {$code}.");

			return self::SUCCESS;
		}

		$token = $this->option('jeton') ?? $this->tokenFor($badges);

		if ($token === null) {
			$this->error('Indique --joueur=<id> ou --jeton=<jeton>.');
			$client->disconnect();

			return self::FAILURE;
		}

		$reply = $this->sendPassage($client, "{$prefix}/{$code}", $token);
		$client->disconnect();

		if ($reply === null) {
			$this->error('Pas de réponse : l\'API écoute-t-elle les bornes (php artisan bornes:ecouter) ?');

			return self::FAILURE;
		}

		$this->line("Écran de la borne : {$reply['message']}");
		$this->line("Statut : {$reply['status']}, XP : {$reply['reward_xp']}");

		return self::SUCCESS;
	}

	private function tokenFor(BadgeTokenService $badges): ?string
	{
		$player = $this->option('joueur') !== null ? User::find($this->option('joueur')) : null;

		return $player === null ? null : $badges->issue($player)['token'];
	}

	private function sendPassage(MqttClient $client, string $topic, string $token): ?array
	{
		$passageId = (string) Str::uuid();
		$reply = null;

		$client->registerMessageReceivedEventHandler(function (MqttClient $client, string $replyTopic, string $message) use ($topic, $passageId, &$reply): void {
			$data = json_decode($message, true);

			if ($replyTopic === "{$topic}/affichage" && ($data['passage'] ?? null) === $passageId) {
				$reply = $data;
				$client->interrupt();
			}
		});

		$client->subscribe("{$topic}/affichage", null, Mqtt::QOS_AT_LEAST_ONCE);

		$client->registerLoopEventHandler(function (MqttClient $client, float $elapsedTime): void {
			if ($elapsedTime > self::REPLY_TIMEOUT_SECONDS) {
				$client->interrupt();
			}
		});

		$client->publish("{$topic}/passages", json_encode(['id' => $passageId, 'token' => $token, 'scanned_at' => now()->toIso8601String()]), Mqtt::QOS_AT_LEAST_ONCE);
		$client->loop(true);

		return $reply;
	}
}
