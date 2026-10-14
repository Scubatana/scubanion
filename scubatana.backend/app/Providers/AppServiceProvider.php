<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

class AppServiceProvider extends ServiceProvider
{
	public function register(): void
	{
	}

	public function boot(): void
	{
		Mail::extend('brevo', fn (): TransportInterface => (new BrevoTransportFactory)->create(
			new Dsn('brevo+api', 'default', (string) config('services.brevo.key')),
		));

		RateLimiter::for('login-code', fn (Request $request): array => [
			Limit::perHour(config('login.requests_per_email_per_hour'))->by('email:'.mb_strtolower(trim((string) $request->input('email')))),
			Limit::perHour(config('login.requests_per_ip_per_hour'))->by('ip:'.$request->ip()),
		]);

		RateLimiter::for('login-verify', fn (Request $request): Limit => Limit::perMinute(config('login.verifications_per_ip_per_minute'))->by('ip:'.$request->ip()));

		RateLimiter::for('friend-invite', fn (Request $request): Limit => Limit::perMinute(20)->by('user:'.$request->user()?->id));
	}
}
