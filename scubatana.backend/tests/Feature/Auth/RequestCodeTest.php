<?php

namespace Tests\Feature\Auth;

use App\Mail\LoginCodeMail;
use App\Models\LoginCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RequestCodeTest extends TestCase
{
	use RefreshDatabase;

	public function test_it_mails_a_code_to_the_given_address(): void
	{
		Mail::fake();

		$this->postJson('/api/auth/code', ['email' => 'lea@example.com'])->assertNoContent();

		$this->assertSame(1, LoginCode::where('email', 'lea@example.com')->count());
		Mail::assertSent(LoginCodeMail::class, fn (LoginCodeMail $mail) => $mail->hasTo('lea@example.com') && preg_match('/^\d{6}$/', $mail->code) === 1);
	}

	public function test_the_mail_shows_the_code(): void
	{
		$mail = new LoginCodeMail('482913', 10);

		$mail->assertSeeInHtml('482913');
		$mail->assertSeeInText('482913');
		$mail->assertSeeInText('10 minutes');
	}

	public function test_it_normalizes_the_email(): void
	{
		Mail::fake();

		$this->postJson('/api/auth/code', ['email' => '  Lea@Example.COM '])->assertNoContent();

		$this->assertSame('lea@example.com', LoginCode::sole()->email);
	}

	public function test_it_rejects_an_invalid_email(): void
	{
		Mail::fake();

		$this->postJson('/api/auth/code', ['email' => 'pas-un-email'])->assertUnprocessable()->assertJsonValidationErrors('email');
		$this->postJson('/api/auth/code', [])->assertUnprocessable()->assertJsonValidationErrors('email');

		Mail::assertNothingSent();
	}

	public function test_it_throttles_requests_per_email(): void
	{
		Mail::fake();

		foreach (range(1, 5) as $attempt) {
			$this->postJson('/api/auth/code', ['email' => 'lea@example.com'])->assertNoContent();
		}

		$this->postJson('/api/auth/code', ['email' => 'lea@example.com'])->assertTooManyRequests();
		Mail::assertSent(LoginCodeMail::class, 5);
	}

	public function test_it_throttles_requests_per_ip(): void
	{
		Mail::fake();

		foreach (range(1, 10) as $attempt) {
			$this->postJson('/api/auth/code', ['email' => "joueur{$attempt}@example.com"])->assertNoContent();
		}

		$this->postJson('/api/auth/code', ['email' => 'joueur11@example.com'])->assertTooManyRequests();
	}
}
