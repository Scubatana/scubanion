<?php

namespace App\Services;

use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccountService
{
	public function delete(User $user): void
	{
		DB::transaction(function () use ($user): void {
			$user->tokens()->delete();
			LoginCode::where('email', $user->email)->delete();
			DB::table('sessions')->where('user_id', $user->id)->delete();
			$user->delete();
		});
	}
}
