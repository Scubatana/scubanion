<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class BadgeTokenService
{
	public function issue(User $user): array
	{
		$expiresAt = CarbonImmutable::now()->addSeconds(config('bornes.badge_ttl_seconds'));
		$payload = base_convert((string) $user->id, 10, 36).'.'.base_convert((string) $expiresAt->getTimestamp(), 10, 36);

		return ['token' => $payload.'.'.$this->sign($payload), 'expires_at' => $expiresAt];
	}

	public function verify(string $token, CarbonInterface $at): ?User
	{
		if (! preg_match('/^([0-9a-z]+)\.([0-9a-z]+)\.([A-Za-z0-9_-]+)$/', $token, $parts)) {
			return null;
		}

		[, $id, $expires, $signature] = $parts;

		if (! hash_equals($this->sign($id.'.'.$expires), $signature) || $at->getTimestamp() > (int) base_convert($expires, 36, 10)) {
			return null;
		}

		return User::find((int) base_convert($id, 36, 10));
	}

	private function sign(string $payload): string
	{
		$key = hash_hmac('sha256', 'scubanion-badge', (string) config('app.key'), true);

		return rtrim(strtr(base64_encode(substr(hash_hmac('sha256', $payload, $key, true), 0, 12)), '+/', '-_'), '=');
	}
}
