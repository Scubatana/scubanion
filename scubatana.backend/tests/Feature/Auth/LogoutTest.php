<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LogoutTest extends TestCase
{
	use RefreshDatabase;

	public function test_the_token_no_longer_works_after_logout(): void
	{
		$token = User::factory()->create()->createToken('mobile')->plainTextToken;

		$this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

		Auth::forgetGuards();
		$this->withToken($token)->getJson('/api/me')->assertUnauthorized();
	}

	public function test_logout_requires_a_token(): void
	{
		$this->postJson('/api/auth/logout')->assertUnauthorized();
	}
}
