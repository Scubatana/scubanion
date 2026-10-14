<?php

namespace Tests\Feature\Bornes;

use App\Enums\TerminalStatus;
use App\Models\Companion;
use App\Models\Passage;
use App\Models\Terminal;
use App\Models\User;
use App\Services\BorneMessageHandler;
use App\Services\TerminalDisplay;
use App\Services\TerminalTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class PassageTest extends TestCase
{
	use RefreshDatabase;

	private Terminal $terminal;

	private User $lea;

	private MockInterface $display;

	protected function setUp(): void
	{
		parent::setUp();

		$this->travelTo('2026-10-13 15:00:00');
		$this->terminal = Terminal::factory()->create(['code' => 'chateau-01', 'name' => 'Borne du Château']);
		$this->lea = User::factory()->create(['pseudo' => 'Lea']);
		$this->display = $this->mock(TerminalDisplay::class);
		$this->display->shouldReceive('show')->byDefault();
		Sanctum::actingAs($this->lea);
	}

	private function token(?string $scannedAt = null, ?Terminal $terminal = null): string
	{
		return app(TerminalTokenService::class)->generate($terminal ?? $this->terminal, $scannedAt === null ? now() : now()->parse($scannedAt));
	}

	private function send(string $token)
	{
		return $this->postJson('/api/me/passages', ['token' => $token]);
	}

	public function test_it_requires_a_token(): void
	{
		$this->app['auth']->forgetGuards();

		$this->withHeader('Authorization', 'Bearer faux')->postJson('/api/me/passages', ['token' => $this->token()])->assertUnauthorized();
	}

	public function test_a_first_passage_of_the_day_gives_a_reward_and_is_shown_on_the_terminal(): void
	{
		$this->display->shouldReceive('show')->once()->withArgs(fn (Terminal $terminal, array $display) => $terminal->is($this->terminal)
			&& $display === ['scanned_at' => '2026-10-13T15:00:00+02:00', 'status' => 'rewarded', 'message' => 'Bravo Lea ! +100 XP', 'reward_xp' => 100]);

		$this->send($this->token())
			->assertOk()
			->assertJsonPath('status', 'rewarded')
			->assertJsonPath('message', 'Bravo Lea ! +100 XP')
			->assertJsonPath('reward_xp', 100)
			->assertJsonPath('terminal', ['code' => 'chateau-01', 'name' => 'Borne du Château']);

		$passage = Passage::sole();
		$this->assertTrue($passage->user->is($this->lea));
		$this->assertSame('2026-10-13 15:00:00', $passage->scanned_at->toDateTimeString());
	}

	public function test_a_second_passage_the_same_day_is_recorded_without_reward(): void
	{
		$this->send($this->token())->assertOk();
		$this->travel(2)->hours();

		$this->send($this->token())
			->assertOk()
			->assertJsonPath('status', 'already_rewarded')
			->assertJsonPath('message', "Déjà passé aujourd'hui, Lea. Reviens demain !")
			->assertJsonPath('reward_xp', 0);

		$this->travelTo('2026-10-14 09:00:00');

		$this->send($this->token())->assertOk()->assertJsonPath('status', 'rewarded');
		$this->assertSame([100, 0, 100], Passage::orderBy('id')->pluck('reward_xp')->all());
	}

	public function test_a_token_can_only_be_used_once_even_by_another_player(): void
	{
		$token = $this->token();

		$this->send($token)->assertOk();
		$this->send($token)->assertStatus(409)->assertJsonPath('message', 'Ce code de borne a déjà été utilisé.');

		Sanctum::actingAs(User::factory()->create(['pseudo' => 'Yanis']));
		$this->send($token)->assertStatus(409);

		$this->assertSame(1, Passage::count());
	}

	public function test_a_forged_or_damaged_token_is_refused(): void
	{
		$token = $this->token();
		[$code, $time, $signature] = explode('.', $token);
		$other = Terminal::factory()->create(['code' => 'parc-01']);

		$this->send("{$code}.".base_convert((string) (now()->getTimestamp() + 60), 10, 36).".{$signature}")->assertUnprocessable()->assertJsonValidationErrors('token');
		$this->send("parc-01.{$time}.{$signature}")->assertUnprocessable()->assertJsonValidationErrors('token');
		$this->send("inconnue.{$time}.{$signature}")->assertUnprocessable()->assertJsonValidationErrors('token');
		$this->send('pas un jeton')->assertUnprocessable()->assertJsonValidationErrors('token');
		$this->postJson('/api/me/passages', [])->assertUnprocessable()->assertJsonValidationErrors('token');

		$this->send($this->token(null, $other))->assertOk();
		$this->assertSame(1, Passage::count());
	}

	public function test_a_token_scanned_offline_is_accepted_for_a_day(): void
	{
		$this->send($this->token('2026-10-12 15:30:00'))->assertOk()->assertJsonPath('status', 'rewarded');
		$this->send($this->token('2026-10-13 15:00:00'))->assertOk()->assertJsonPath('status', 'rewarded');

		$this->send($this->token('2026-10-12 14:59:00'))->assertUnprocessable()->assertJsonValidationErrors('token');
		$this->send($this->token('2026-10-13 15:06:00'))->assertUnprocessable()->assertJsonValidationErrors('token');
		$this->assertSame(2, Passage::count());
	}

	public function test_the_reward_goes_to_the_companion_progression(): void
	{
		$companion = Companion::factory()->for($this->lea)->create();

		$this->send($this->token())->assertOk()->assertJsonPath('companion.xp', 100);

		$this->assertSame(100, $companion->fresh()->xp);
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
		$this->send($this->token())->assertOk();

		$this->lea->delete();

		$this->assertSame(0, Passage::count());
	}
}
