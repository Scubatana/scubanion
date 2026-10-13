<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class FriendCode
{
	public const LENGTH = 8;

	private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

	public static function generate(): string
	{
		do {
			$code = '';

			for ($i = 0; $i < self::LENGTH; $i++) {
				$code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
			}
		} while (DB::table('users')->where('friend_code', $code)->exists());

		return $code;
	}

	public static function normalize(string $code): string
	{
		return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
	}
}
