<?php

namespace App\Services;

use App\Models\LoginCode;
use Illuminate\Support\Facades\Hash;

class LoginCodeService
{
	public function issue(string $email): string
	{
		LoginCode::where('email', $email)->delete();

		$code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

		LoginCode::create([
			'email' => $email,
			'code_hash' => Hash::make($code),
			'expires_at' => now()->addMinutes(config('login.code_ttl_minutes')),
		]);

		return $code;
	}

	public function verify(string $email, string $code): bool
	{
		$loginCode = LoginCode::where('email', $email)
			->whereNull('consumed_at')
			->where('expires_at', '>', now())
			->where('attempts', '<', config('login.max_attempts'))
			->latest('id')
			->first();

		if ($loginCode === null) {
			return false;
		}

		if (! Hash::check($code, $loginCode->code_hash)) {
			$loginCode->increment('attempts');

			return false;
		}

		$loginCode->update(['consumed_at' => now()]);

		return true;
	}
}
