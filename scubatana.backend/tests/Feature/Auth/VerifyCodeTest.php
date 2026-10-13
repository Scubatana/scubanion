<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\LoginCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyCodeTest extends TestCase
{
	use RefreshDatabase;

	private const EMAIL = 'lea@example.com';

	private function issueCode(): string
	{
		return app(LoginCodeService::class)->issue(self::EMAIL);
	}

	private function wrongCodeFor(string $code): string
	{
		return $code === '000000' ? '111111' : '000000';
	}

	public function test_it_creates_the_account_on_first_login(): void
	{
		$response = $this->postJson('/api/auth/verify', [
			'email' => self::EMAIL,
			'code' => $this->issueCode(),
			'terms_accepted' => true,
		]);

		$response->assertOk()
			->assertJsonPath('player.email', self::EMAIL)
			->assertJsonPath('player.profile_completed', false)
			->assertJsonPath('player.pseudo', null);

		$user = User::sole();
		$this->assertNotNull($user->terms_accepted_at);
		$this->withToken($response->json('token'))->getJson('/api/me')->assertOk();
	}

	public function test_it_logs_an_existing_player_in(): void
	{
		$user = User::factory()->create(['email' => self::EMAIL]);

		$this->postJson('/api/auth/verify', ['email' => self::EMAIL, 'code' => $this->issueCode()])
			->assertOk()
			->assertJsonPath('player.id', $user->id)
			->assertJsonPath('player.profile_completed', true);

		$this->assertSame(1, User::count());
	}

	public function test_it_normalizes_the_email_and_the_code(): void
	{
		$code = $this->issueCode();

		$this->postJson('/api/auth/verify', [
			'email' => ' Lea@Example.com ',
			'code' => substr($code, 0, 3).' '.substr($code, 3),
			'terms_accepted' => true,
		])->assertOk()->assertJsonPath('player.email', self::EMAIL);
	}

	public function test_it_rejects_a_wrong_code(): void
	{
		$wrong = $this->wrongCodeFor($this->issueCode());

		$this->postJson('/api/auth/verify', ['email' => self::EMAIL, 'code' => $wrong, 'terms_accepted' => true])
			->assertUnprocessable()
			->assertJsonValidationErrors('code');

		$this->assertSame(0, User::count());
	}

	public function test_it_rejects_an_expired_code(): void
	{
		$code = $this->issueCode();
		$this->travel(11)->minutes();

		$this->postJson('/api/auth/verify', ['email' => self::EMAIL, 'code' => $code, 'terms_accepted' => true])
			->assertUnprocessable()
			->assertJsonValidationErrors('code');
	}

	public function test_it_rejects_a_code_already_used(): void
	{
		$payload = ['email' => self::EMAIL, 'code' => $this->issueCode(), 'terms_accepted' => true];

		$this->postJson('/api/auth/verify', $payload)->assertOk();
		$this->postJson('/api/auth/verify', $payload)->assertUnprocessable()->assertJsonValidationErrors('code');
	}

	public function test_it_locks_the_code_after_five_wrong_attempts(): void
	{
		$code = $this->issueCode();
		$wrong = $this->wrongCodeFor($code);

		foreach (range(1, 5) as $attempt) {
			$this->postJson('/api/auth/verify', ['email' => self::EMAIL, 'code' => $wrong, 'terms_accepted' => true])->assertUnprocessable();
		}

		$this->postJson('/api/auth/verify', ['email' => self::EMAIL, 'code' => $code, 'terms_accepted' => true])
			->assertUnprocessable()
			->assertJsonValidationErrors('code');
	}

	public function test_a_new_account_requires_the_terms_without_consuming_the_code(): void
	{
		$code = $this->issueCode();

		$this->postJson('/api/auth/verify', ['email' => self::EMAIL, 'code' => $code, 'terms_accepted' => false])
			->assertUnprocessable()
			->assertJsonValidationErrors('terms_accepted');
		$this->assertSame(0, User::count());

		$this->postJson('/api/auth/verify', ['email' => self::EMAIL, 'code' => $code, 'terms_accepted' => true])->assertOk();
	}

	public function test_a_wrong_code_never_reveals_whether_an_account_exists(): void
	{
		$unknown = $this->postJson('/api/auth/verify', ['email' => self::EMAIL, 'code' => $this->wrongCodeFor($this->issueCode()), 'terms_accepted' => false]);

		User::factory()->create(['email' => self::EMAIL]);
		$known = $this->postJson('/api/auth/verify', ['email' => self::EMAIL, 'code' => $this->wrongCodeFor($this->issueCode()), 'terms_accepted' => false]);

		$unknown->assertUnprocessable()->assertJsonValidationErrors('code')->assertJsonMissingValidationErrors('terms_accepted');
		$known->assertUnprocessable()->assertJsonValidationErrors('code')->assertJsonMissingValidationErrors('terms_accepted');
		$this->assertSame($unknown->json(), $known->json());
	}

	public function test_it_rejects_a_malformed_code(): void
	{
		$this->postJson('/api/auth/verify', ['email' => self::EMAIL, 'code' => '123', 'terms_accepted' => true])
			->assertUnprocessable()
			->assertJsonValidationErrors('code');
	}
}
