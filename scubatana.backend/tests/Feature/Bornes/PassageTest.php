<?php

namespace Tests\Feature\Bornes;

use App\Enums\TerminalStatus;
use App\Models\Companion;
use App\Models\Passage;
use App\Models\Terminal;
use App\Models\User;
use App\Services\BadgeTokenService;
use App\Services\BorneMessageHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassageTest extends TestCase
{
	use RefreshDatabase;

	private Terminal $terminal;

	private User $lea;

	protected function setUp(): void
	{
		parent::setUp();

		$this->travelTo('2026-10-13 15:00:00');
		$this->terminal = Terminal::factory()->create(['code' => 'chateau-01']);
		$this->lea = User::factory()->create(['pseudo' => 'Lea']);
	}

	private function scan(string $id, ?string $token = null, ?string $scannedAt = null, string $terminal = 'chateau-01'): ?array
	{
		$message = json_encode([
			'id' => $id,
			'token' => $token ?? app(BadgeTokenService::class)->issue($this->lea)['token'],
			'scanned_at' => $scannedAt ?? now()->toIso8601String(),
		]);

		return app(BorneMessageHandler::class)->handlePassage($terminal, $message);
	}

	public function test_a_first_passage_of_the_day_gives_a_reward(): void
	{
		$this->assertSame(['passage' => 'p-1', 'status' => 'rewarded', 'message' => 'Bravo Lea ! +100 XP', 'reward_xp' => 100], $this->scan('p-1'));

		$passage = Passage::sole();
		$this->assertTrue($passage->user->is($this->lea));
		$this->assertSame(100, $passage->reward_xp);
		$this->assertSame(TerminalStatus::Online, $this->terminal->fresh()->status);
	}

	public function test_the_reward_goes_to_the_companion_progression(): void
	{
		$companion = Companion::factory()->for($this->lea)->create();

		$this->scan('p-1');

		$this->assertSame(100, $companion->fresh()->xp);
	}

	public function test_a_second_passage_the_same_day_is_recorded_without_reward(): void
	{
		$this->scan('p-1');

		$this->travelTo('2026-10-13 18:00:00');
		$this->assertSame('already_rewarded', $this->scan('p-2')['status']);

		$this->travelTo('2026-10-14 09:00:00');
		$this->assertSame('rewarded', $this->scan('p-3')['status']);

		$this->assertSame([100, 0, 100], Passage::orderBy('id')->pluck('reward_xp')->all());
	}

	public function test_a_passage_sent_twice_is_counted_once_and_answered_the_same_way(): void
	{
		$first = $this->scan('p-1');

		$this->assertSame($first, $this->scan('p-1'));
		$this->assertSame(1, Passage::count());
	}

	public function test_passages_stored_offline_are_accepted_within_a_day(): void
	{
		$token = app(BadgeTokenService::class)->issue($this->lea)['token'];

		$this->travelTo('2026-10-14 10:00:00');

		$this->assertSame('rewarded', $this->scan('p-1', $token, '2026-10-13T15:01:00+02:00')['status']);
		$this->assertSame('2026-10-13 15:01:00', Passage::sole()->scanned_at->toDateTimeString());
		$this->assertSame('invalid_message', $this->scan('p-2', $token, '2026-10-13T09:00:00+02:00')['status']);
	}

	public function test_a_badge_that_expired_before_the_scan_is_refused(): void
	{
		$token = app(BadgeTokenService::class)->issue($this->lea)['token'];

		$this->travelTo('2026-10-13 15:10:00');

		$this->assertSame(['passage' => 'p-1', 'status' => 'invalid_token', 'message' => 'Badge non reconnu.', 'reward_xp' => 0], $this->scan('p-1', $token));
		$this->assertSame(0, Passage::count());
	}

	public function test_an_unreadable_message_is_answered_without_being_recorded(): void
	{
		$handler = app(BorneMessageHandler::class);

		$this->assertSame('invalid_message', $handler->handlePassage('chateau-01', 'pas du json')['status']);
		$this->assertSame('invalid_message', $handler->handlePassage('chateau-01', json_encode(['id' => 'p-1']))['status']);
		$this->assertSame('invalid_message', $this->scan('p-1', null, '2026-10-13T16:00:00+02:00')['status']);
		$this->assertSame(0, Passage::count());
	}

	public function test_an_unknown_terminal_is_ignored(): void
	{
		$this->assertNull($this->scan('p-1', null, null, 'borne-inconnue'));
		$this->assertSame(0, Passage::count());
	}

	public function test_the_heartbeat_tells_whether_a_terminal_is_online(): void
	{
		$handler = app(BorneMessageHandler::class);

		$handler->handleState('chateau-01', json_encode(['status' => 'online', 'firmware' => '1.2.0']));

		$terminal = $this->terminal->fresh();
		$this->assertSame(TerminalStatus::Online, $terminal->status);
		$this->assertSame('1.2.0', $terminal->firmware);
		$this->assertTrue($terminal->isOnline());

		$this->travelTo('2026-10-13 15:03:00');
		$this->assertFalse($terminal->fresh()->isOnline());

		$handler->handleState('chateau-01', json_encode(['status' => 'offline']));
		$this->assertSame(TerminalStatus::Offline, $this->terminal->fresh()->status);

		$handler->handleState('chateau-01', 'pas du json');
		$this->assertSame(TerminalStatus::Offline, $this->terminal->fresh()->status);
	}

	public function test_passages_go_away_with_the_player(): void
	{
		$this->scan('p-1');

		$this->lea->delete();

		$this->assertSame(0, Passage::count());
	}
}
