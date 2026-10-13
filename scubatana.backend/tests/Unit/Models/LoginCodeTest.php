<?php

namespace Tests\Unit\Models;

use App\Models\LoginCode;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginCodeTest extends TestCase
{
	use RefreshDatabase;

	private function codeExpiringAt(string $email, string $expiresAt): LoginCode
	{
		return LoginCode::create(['email' => $email, 'code_hash' => 'hash', 'expires_at' => $expiresAt]);
	}

	public function test_pruning_forgets_the_codes_expired_for_more_than_a_day(): void
	{
		$this->travelTo('2026-10-13 12:00:00');
		$this->codeExpiringAt('ancien@example.com', '2026-10-12 11:59:00');
		$recent = $this->codeExpiringAt('recent@example.com', '2026-10-13 11:00:00');
		$valid = $this->codeExpiringAt('valide@example.com', '2026-10-13 12:10:00');

		$this->artisan('model:prune', ['--model' => [LoginCode::class]])->assertSuccessful();

		$this->assertEqualsCanonicalizing([$recent->id, $valid->id], LoginCode::pluck('id')->all());
	}

	public function test_pruning_runs_every_night(): void
	{
		$events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains($event->command, 'model:prune'));

		$this->assertCount(1, $events);
		$this->assertSame('0 0 * * *', $events->first()->expression);
	}
}
