<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\BadgeTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BadgeTokenServiceTest extends TestCase
{
	use RefreshDatabase;

	public function test_a_badge_names_its_player_until_it_expires(): void
	{
		$this->travelTo('2026-10-13 15:00:00');
		$player = User::factory()->create();
		$badges = app(BadgeTokenService::class);

		$badge = $badges->issue($player);

		$this->assertSame('2026-10-13 15:05:00', $badge['expires_at']->toDateTimeString());
		$this->assertLessThanOrEqual(40, strlen($badge['token']));
		$this->assertTrue($badges->verify($badge['token'], now())->is($player));
		$this->assertTrue($badges->verify($badge['token'], now()->addMinutes(5))->is($player));
		$this->assertNull($badges->verify($badge['token'], now()->addMinutes(5)->addSecond()));
	}

	public function test_a_badge_stays_valid_for_a_passage_scanned_before_it_expired(): void
	{
		$this->travelTo('2026-10-13 15:00:00');
		$player = User::factory()->create();
		$badges = app(BadgeTokenService::class);
		$token = $badges->issue($player)['token'];

		$this->travelTo('2026-10-13 21:00:00');

		$this->assertTrue($badges->verify($token, now()->setTime(15, 2))->is($player));
	}

	public function test_a_forged_or_damaged_badge_is_refused(): void
	{
		$badges = app(BadgeTokenService::class);
		$token = $badges->issue(User::factory()->create())['token'];
		[$id, $expires, $signature] = explode('.', $token);
		$other = User::factory()->create();

		$this->assertNull($badges->verify(base_convert((string) $other->id, 10, 36).".{$expires}.{$signature}", now()));
		$this->assertNull($badges->verify("{$id}.zzzzzzz.{$signature}", now()));
		$this->assertNull($badges->verify("{$id}.{$expires}.AAAAAAAAAAAAAAAA", now()));
		$this->assertNull($badges->verify('pas-un-jeton', now()));
	}
}
