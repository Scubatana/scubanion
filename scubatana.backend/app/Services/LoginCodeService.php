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

	public function find(string $email, string $code): ?LoginCode
	{
		$loginCode = LoginCode::where('email', $email)
			->whereNull('consumed_at')
			->where('expires_at', '>', now())
			->where('attempts', '<', config('login.max_attempts'))
			->latest('id')
			->first();

		if ($loginCode === null) {
			return null;
		}

		if (! Hash::check($code, $loginCode->code_hash)) {
			$loginCode->increment('attempts');

			return null;
		}

		return $loginCode;
	}

	public function consume(LoginCode $loginCode): void
	{
		$loginCode->update(['consumed_at' => now()]);
	}

	public function verify(string $email, string $code): bool
	{
		$loginCode = $this->find($email, $code);

		if ($loginCode === null) {
			return false;
		}

		$this->consume($loginCode);

		return true;
	}
}
