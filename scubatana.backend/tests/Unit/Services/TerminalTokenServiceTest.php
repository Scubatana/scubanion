<?php

namespace Tests\Unit\Services;

use App\Models\Terminal;
use App\Services\TerminalTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TerminalTokenServiceTest extends TestCase
{
	use RefreshDatabase;

	public function test_a_token_names_its_terminal_and_the_time_of_the_scan(): void
	{
		$this->travelTo('2026-10-13 15:00:00');
		$terminal = Terminal::factory()->create(['code' => 'chateau-01']);
		$tokens = app(TerminalTokenService::class);

		$token = $tokens->generate($terminal, now());
		$read = $tokens->read($token);

		$this->assertMatchesRegularExpression('/^chateau-01\.[0-9a-z]+\.[A-Za-z0-9_-]{16}$/', $token);
		$this->assertLessThanOrEqual(40, strlen($token));
		$this->assertTrue($read['terminal']->is($terminal));
		$this->assertSame('2026-10-13 15:00:00', $read['scanned_at']->toDateTimeString());
	}

	public function test_a_token_needs_the_secret_of_its_own_terminal(): void
	{
		$terminal = Terminal::factory()->create(['code' => 'chateau-01']);
		$tokens = app(TerminalTokenService::class);
		$token = $tokens->generate($terminal, now());

		$terminal->update(['secret' => TerminalTokenService::newSecret()]);

		$this->assertNull($tokens->read($token));
	}

	public function test_the_secret_is_stored_encrypted_and_never_serialized(): void
	{
		$terminal = Terminal::factory()->create(['secret' => 'secret-de-la-borne']);

		$this->assertNotSame('secret-de-la-borne', $terminal->getRawOriginal('secret'));
		$this->assertSame('secret-de-la-borne', $terminal->fresh()->secret);
		$this->assertArrayNotHasKey('secret', $terminal->toArray());
	}
}
