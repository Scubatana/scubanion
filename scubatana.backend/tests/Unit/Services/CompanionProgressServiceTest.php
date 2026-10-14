<?php

namespace Tests\Unit\Services;

use App\Services\CompanionProgressService;
use Tests\TestCase;

class CompanionProgressServiceTest extends TestCase
{
	public function test_each_level_asks_a_thousand_xp_more_than_the_previous_one(): void
	{
		$progress = app(CompanionProgressService::class);

		$this->assertSame([1, 0], $progress->levelFor(0));
		$this->assertSame([1, 999], $progress->levelFor(999));
		$this->assertSame([2, 0], $progress->levelFor(1000));
		$this->assertSame([2, 1999], $progress->levelFor(2999));
		$this->assertSame([3, 0], $progress->levelFor(3000));
		$this->assertSame([4, 0], $progress->levelFor(6000));
	}

	public function test_steps_give_one_xp_per_ten_steps_and_a_bonus_when_the_goal_is_reached(): void
	{
		$days = collect([
			'2026-10-11' => ['steps' => 5209, 'goal' => 5000],
			'2026-10-12' => ['steps' => 4999, 'goal' => 5000],
		]);

		$this->assertSame(520 + 50 + 499, app(CompanionProgressService::class)->totalXp($days));
	}
}
