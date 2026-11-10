<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\FriendQrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class FriendQrServiceTest extends TestCase
{
	use RefreshDatabase;

	private FriendQrService $qr;

	protected function setUp(): void
	{
		parent::setUp();

		$this->travelTo('2026-11-09 16:00:00');
		$this->qr = new FriendQrService;
	}

	public function test_a_token_leads_back_to_the_player_who_displayed_it(): void
	{
		$player = User::factory()->create();

		$issued = $this->qr->issue($player);

		$this->assertTrue($this->qr->resolve($issued['token'])->is($player));
		$this->assertSame('2026-11-09T16:01:00+01:00', $issued['expires_at']);
	}

	public function test_the_token_does_not_show_the_player_in_clear(): void
	{
		$player = User::factory()->create();

		$token = $this->qr->issue($player)['token'];

		$this->assertStringNotContainsString($player->friend_code, $token);
		$this->assertStringNotContainsString('"player"', base64_decode($token));
	}

	public function test_a_token_expires_after_sixty_seconds(): void
	{
		$player = User::factory()->create();
		$token = $this->qr->issue($player)['token'];

		$this->travel(59)->seconds();
		$this->assertNotNull($this->qr->resolve($token));

		$this->travel(2)->seconds();
		$this->assertNull($this->qr->resolve($token));
	}

	public function test_a_garbage_or_tampered_token_is_refused(): void
	{
		$token = $this->qr->issue(User::factory()->create())['token'];

		$this->assertNull($this->qr->resolve('pas-un-jeton'));
		$this->assertNull($this->qr->resolve(substr($token, 0, -4).'AAAA'));
	}

	public function test_another_encrypted_value_is_not_a_qr_token(): void
	{
		$player = User::factory()->create();

		$this->assertNull($this->qr->resolve(Crypt::encryptString(json_encode(['player' => $player->id, 'expires' => now()->addMinute()->getTimestamp()]))));
		$this->assertNull($this->qr->resolve(Crypt::encryptString('bonjour')));
	}

	public function test_a_token_of_a_deleted_player_is_refused(): void
	{
		$player = User::factory()->create();
		$token = $this->qr->issue($player)['token'];

		$player->delete();

		$this->assertNull($this->qr->resolve($token));
	}
}
