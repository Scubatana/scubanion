<?php

namespace Tests\Unit\Services;

use App\Models\LoginCode;
use App\Services\LoginCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginCodeServiceTest extends TestCase
{
	use RefreshDatabase;

	private const EMAIL = 'lea@example.com';

	public function test_issue_stores_a_hashed_six_digit_code(): void
	{
		$this->freezeTime();

		$code = app(LoginCodeService::class)->issue(self::EMAIL);
		$stored = LoginCode::sole();

		$this->assertMatchesRegularExpression('/^\d{6}$/', $code);
		$this->assertNotSame($code, $stored->code_hash);
		$this->assertTrue(Hash::check($code, $stored->code_hash));
		$this->assertSame(now()->addMinutes(10)->timestamp, $stored->expires_at->timestamp);
	}

	public function test_issue_invalidates_the_previous_code(): void
	{
		$service = app(LoginCodeService::class);
		$first = $service->issue(self::EMAIL);
		$second = $service->issue(self::EMAIL);

		$this->assertSame(1, LoginCode::count());
		if ($first !== $second) {
			$this->assertFalse($service->verify(self::EMAIL, $first));
		}
		$this->assertTrue($service->verify(self::EMAIL, $second));
	}

	public function test_verify_rejects_a_code_issued_for_another_email(): void
	{
		$service = app(LoginCodeService::class);
		$code = $service->issue(self::EMAIL);

		$this->assertFalse($service->verify('yanis@example.com', $code));
	}
}
