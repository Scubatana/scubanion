<?php

namespace App\Services;

use App\Models\Terminal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class TerminalTokenService
{
	private const PATTERN = '/^([a-z0-9-]{3,32})\.([0-9a-z]{1,8})\.([A-Za-z0-9_-]{16})$/';

	public static function newSecret(): string
	{
		return self::base64Url(random_bytes(32));
	}

	public function generate(Terminal $terminal, CarbonInterface $scannedAt): string
	{
		$payload = $terminal->code.'.'.base_convert((string) $scannedAt->getTimestamp(), 10, 36);

		return $payload.'.'.$this->sign($terminal->secret, $payload);
	}

	public function read(string $token): ?array
	{
		if (! preg_match(self::PATTERN, $token, $parts)) {
			return null;
		}

		[, $code, $time, $signature] = $parts;
		$terminal = Terminal::where('code', $code)->first();

		if ($terminal === null || ! hash_equals($this->sign($terminal->secret, "{$code}.{$time}"), $signature)) {
			return null;
		}

		return [
			'terminal' => $terminal,
			'scanned_at' => CarbonImmutable::createFromTimestamp((int) base_convert($time, 36, 10))->setTimezone(config('app.timezone')),
		];
	}

	private function sign(string $secret, string $payload): string
	{
		return self::base64Url(substr(hash_hmac('sha256', $payload, $secret, true), 0, 12));
	}

	private static function base64Url(string $bytes): string
	{
		return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
	}
}
