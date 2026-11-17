<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * QR d'un joueur, valable 60 secondes. Son usage est écrit dans le jeton et chaque route vérifie le sien :
 * un QR montré pour une rencontre ne permet pas de devenir ami, et inversement.
 */
class FriendQrService
{
	public const TTL_SECONDS = 60;

	public const FRIEND = 'friend-qr';

	public const MEET = 'meet-qr';

	public function issue(User $player, string $purpose = self::FRIEND): array
	{
		$expiresAt = now()->addSeconds(self::TTL_SECONDS);

		return [
			'token' => Crypt::encryptString(json_encode([
				'purpose' => $purpose,
				'player' => $player->id,
				'expires' => $expiresAt->getTimestamp(),
			])),
			'expires_at' => $expiresAt->toIso8601String(),
		];
	}

	public function resolve(string $token, string $purpose = self::FRIEND): ?User
	{
		try {
			$payload = json_decode(Crypt::decryptString($token), true);
		} catch (DecryptException) {
			return null;
		}

		if (! is_array($payload) || ($payload['purpose'] ?? null) !== $purpose || ! is_int($payload['player'] ?? null) || ! is_int($payload['expires'] ?? null)) {
			return null;
		}

		if (Carbon::createFromTimestamp($payload['expires'])->isPast()) {
			return null;
		}

		return User::whereKey($payload['player'])->whereNotNull('pseudo')->first();
	}
}
