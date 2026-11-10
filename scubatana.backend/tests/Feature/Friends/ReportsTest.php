<?php

namespace Tests\Feature\Friends;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Block;
use App\Models\Friendship;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsTest extends TestCase
{
	use RefreshDatabase;

	private User $lea;

	private User $yanis;

	protected function setUp(): void
	{
		parent::setUp();

		$this->lea = User::factory()->create(['pseudo' => 'Lea']);
		$this->yanis = User::factory()->create(['pseudo' => 'Yanis']);
	}

	private function report(User $from, array $data)
	{
		Sanctum::actingAs($from);

		return $this->postJson('/api/me/reports', $data);
	}

	public function test_it_requires_a_token(): void
	{
		$this->postJson('/api/me/reports', ['player_id' => $this->yanis->id, 'reason' => 'pseudo'])->assertUnauthorized();
	}

	public function test_a_player_can_report_another_player(): void
	{
		$this->report($this->lea, ['player_id' => $this->yanis->id, 'reason' => 'behavior', 'comment' => '  Messages insistants.  '])
			->assertCreated()
			->assertJsonPath('message', "Merci, ton signalement a été envoyé. L'équipe va l'examiner.");

		$report = Report::sole();
		$this->assertSame($this->lea->id, $report->user_id);
		$this->assertSame($this->yanis->id, $report->reported_id);
		$this->assertSame('Yanis', $report->reported_pseudo);
		$this->assertSame(ReportReason::Behavior, $report->reason);
		$this->assertSame('Messages insistants.', $report->comment);
		$this->assertSame(ReportStatus::Pending, $report->status);
	}

	public function test_the_comment_is_optional_and_a_blank_one_is_not_kept(): void
	{
		$this->report($this->lea, ['player_id' => $this->yanis->id, 'reason' => 'pseudo'])->assertCreated();
		$this->report($this->lea, ['player_id' => $this->yanis->id, 'reason' => 'other', 'comment' => '   '])->assertCreated();

		$this->assertSame([null, null], Report::orderBy('id')->pluck('comment')->all());
	}

	public function test_it_refuses_a_missing_unknown_or_own_player(): void
	{
		$this->report($this->lea, ['reason' => 'pseudo'])->assertUnprocessable()->assertJsonValidationErrors('player_id');
		$this->report($this->lea, ['player_id' => 999999, 'reason' => 'pseudo'])->assertUnprocessable()->assertJsonValidationErrors('player_id');
		$this->report($this->lea, ['player_id' => $this->lea->id, 'reason' => 'pseudo'])
			->assertUnprocessable()
			->assertJsonValidationErrors(['player_id' => 'Tu ne peux pas te signaler toi-même.']);

		$this->assertSame(0, Report::count());
	}

	public function test_it_refuses_a_missing_or_unknown_reason_and_a_too_long_comment(): void
	{
		$this->report($this->lea, ['player_id' => $this->yanis->id])->assertUnprocessable()->assertJsonValidationErrors('reason');
		$this->report($this->lea, ['player_id' => $this->yanis->id, 'reason' => 'spam'])
			->assertUnprocessable()
			->assertJsonValidationErrors(['reason' => "Ce motif n'existe pas."]);
		$this->report($this->lea, ['player_id' => $this->yanis->id, 'reason' => 'other', 'comment' => str_repeat('a', 501)])
			->assertUnprocessable()
			->assertJsonValidationErrors('comment');

		$this->assertSame(0, Report::count());
	}

	public function test_reporting_does_not_block_nor_remove_the_friendship(): void
	{
		Friendship::create(['user_id' => $this->lea->id, 'friend_id' => $this->yanis->id, 'status' => 'accepted', 'accepted_at' => now()]);

		$this->report($this->lea, ['player_id' => $this->yanis->id, 'reason' => 'pseudo'])->assertCreated();

		$this->assertSame(1, Friendship::count());
		$this->assertSame(0, Block::count());
	}

	public function test_a_player_can_send_five_reports_per_hour(): void
	{
		for ($i = 0; $i < 5; $i++) {
			$this->report($this->lea, ['player_id' => $this->yanis->id, 'reason' => 'pseudo'])->assertCreated();
		}

		$this->report($this->lea, ['player_id' => $this->yanis->id, 'reason' => 'pseudo'])->assertTooManyRequests();
		$this->assertSame(5, Report::count());

		$this->travel(61)->minutes();

		$this->report($this->lea, ['player_id' => $this->yanis->id, 'reason' => 'pseudo'])->assertCreated();
	}
}
