<?php

namespace App\Services;

use App\Models\Terminal;
use Illuminate\Support\Facades\File;
use RuntimeException;

class MqttAccounts
{
	public const ITERATIONS = 1000;

	public const SALT_BYTES = 64;

	public function __construct(private TerminalTokenService $tokens) {}

	public function passwordFile(): string
	{
		$api = $this->apiUsername();
		$password = (string) config('bornes.mqtt.password');

		if ($password === '') {
			throw new RuntimeException('MQTT_PASSWORD manquant : le compte de l\'API ne peut pas être créé sur le broker.');
		}

		$lines = [$api.':'.self::hash($password)];

		foreach (Terminal::orderBy('code')->get() as $terminal) {
			if ($terminal->code === $api) {
				throw new RuntimeException("La borne {$terminal->code} porte le même nom que le compte MQTT de l'API.");
			}

			$lines[] = $terminal->code.':'.self::hash($this->tokens->mqttPassword($terminal));
		}

		return implode("\n", $lines)."\n";
	}

	public function aclFile(): string
	{
		$prefix = config('bornes.topic_prefix');

		return implode("\n", [
			'user '.$this->apiUsername(),
			"topic readwrite {$prefix}/#",
			'topic read $SYS/#',
			'',
			"pattern write {$prefix}/%u/etat",
			"pattern read {$prefix}/%u/affichage",
		])."\n";
	}

	public function write(): string
	{
		$directory = config('bornes.mqtt.accounts_directory');
		File::ensureDirectoryExists($directory);

		$this->replace("{$directory}/passwd", $this->passwordFile());
		$this->replace("{$directory}/acl", $this->aclFile());

		return $directory;
	}

	public static function hash(string $password, ?string $salt = null): string
	{
		$salt ??= random_bytes(self::SALT_BYTES);
		$hash = hash_pbkdf2('sha512', $password, $salt, self::ITERATIONS, 64, true);

		return '$7$'.self::ITERATIONS.'$'.base64_encode($salt).'$'.base64_encode($hash);
	}

	private function apiUsername(): string
	{
		$username = (string) config('bornes.mqtt.username');

		if ($username === '') {
			throw new RuntimeException('MQTT_USERNAME manquant : le compte de l\'API ne peut pas être créé sur le broker.');
		}

		return $username;
	}

	private function replace(string $path, string $contents): void
	{
		file_put_contents("{$path}.tmp", $contents);
		rename("{$path}.tmp", $path);
	}
}
