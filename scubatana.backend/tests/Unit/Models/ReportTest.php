<?php

namespace Tests\Unit\Models;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;
use ValueError;

class ReportTest extends TestCase
{
	use RefreshDatabase;

	public function test_a_new_report_is_pending_and_knows_who_reported_whom(): void
	{
		[$reporter, $reported] = User::factory()->count(2)->create();

		$report = Report::create(['user_id' => $reporter->id, 'reported_id' => $reported->id, 'reason' => ReportReason::Pseudo])->fresh();

		$this->assertSame(ReportReason::Pseudo, $report->reason);
		$this->assertSame(ReportStatus::Pending, $report->status);
		$this->assertNull($report->comment);
		$this->assertTrue($report->reporter->is($reporter));
		$this->assertTrue($report->reported->is($reported));
	}

	public function test_a_player_cannot_report_themselves(): void
	{
		$player = User::factory()->create();

		$this->expectException(InvalidArgumentException::class);

		Report::create(['user_id' => $player->id, 'reported_id' => $player->id, 'reason' => ReportReason::Other]);
	}

	public function test_an_unknown_reason_is_refused(): void
	{
		[$reporter, $reported] = User::factory()->count(2)->create();

		$this->expectException(ValueError::class);

		Report::create(['user_id' => $reporter->id, 'reported_id' => $reported->id, 'reason' => 'spam']);
	}

	public function test_the_same_player_can_be_reported_more_than_once(): void
	{
		[$reporter, $reported] = User::factory()->count(2)->create();

		Report::create(['user_id' => $reporter->id, 'reported_id' => $reported->id, 'reason' => ReportReason::Pseudo]);
		Report::create(['user_id' => $reporter->id, 'reported_id' => $reported->id, 'reason' => ReportReason::Behavior]);

		$this->assertSame(2, Report::count());
	}

	public function test_pending_lists_only_the_reports_still_to_review(): void
	{
		[$reporter, $reported] = User::factory()->count(2)->create();
		$toReview = Report::create(['user_id' => $reporter->id, 'reported_id' => $reported->id, 'reason' => ReportReason::Pseudo]);
		Report::create(['user_id' => $reporter->id, 'reported_id' => $reported->id, 'reason' => ReportReason::Other, 'status' => ReportStatus::Reviewed]);

		$this->assertSame([$toReview->id], Report::pending()->pluck('id')->all());
	}

	public function test_reports_are_deleted_with_either_account(): void
	{
		[$reporter, $reported, $other] = User::factory()->count(3)->create();
		Report::create(['user_id' => $reporter->id, 'reported_id' => $reported->id, 'reason' => ReportReason::Pseudo]);
		Report::create(['user_id' => $other->id, 'reported_id' => $reporter->id, 'reason' => ReportReason::Behavior]);

		$reporter->delete();

		$this->assertSame(0, Report::count());
	}
}
