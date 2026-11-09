<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * Jeton du QR d'ami : l'identifiant du joueur et l'heure d'expiration, chiffrés avec la clé
 * de l'application. Illisible et infalsifiable sans cette clé, il n'a pas besoin de table.
 */
class FriendQrService
{
	public const TTL_SECONDS = 60;

	private const PURPOSE = 'friend-qr';

	public function issue(User $player): array
	{
		$expiresAt = now()->addSeconds(self::TTL_SECONDS);

		return [
			'token' => Crypt::encryptString(json_encode([
				'purpose' => self::PURPOSE,
				'player' => $player->id,
				'expires' => $expiresAt->getTimestamp(),
			])),
			'expires_at' => $expiresAt->toIso8601String(),
		];
	}

	/**
	 * Le joueur qui a affiché ce QR, ou null si le jeton est illisible, falsifié, expiré,
	 * ou si son joueur n'existe plus.
	 */
	public function resolve(string $token): ?User
	{
		try {
			$payload = json_decode(Crypt::decryptString($token), true);
		} catch (DecryptException) {
			return null;
		}

		if (! is_array($payload) || ($payload['purpose'] ?? null) !== self::PURPOSE || ! is_int($payload['player'] ?? null) || ! is_int($payload['expires'] ?? null)) {
			return null;
		}

		if (Carbon::createFromTimestamp($payload['expires'])->isPast()) {
			return null;
		}

		return User::whereKey($payload['player'])->whereNotNull('pseudo')->first();
	}
}
