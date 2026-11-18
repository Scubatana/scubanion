<?php

namespace Tests\Feature\Bornes;

use App\Models\Accessory;
use App\Models\Companion;
use App\Models\Event;
use App\Models\EventPassage;
use App\Models\Passage;
use App\Models\Place;
use App\Models\Terminal;
use App\Models\User;
use App\Services\TerminalDisplay;
use App\Services\TerminalTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class EventPassageTest extends TestCase
{
	use RefreshDatabase;

	private Terminal $terminal;

	private User $lea;

	private MockInterface $display;

	protected function setUp(): void
	{
		parent::setUp();

		$this->travelTo('2026-10-17 15:00:00');
		$this->terminal = Terminal::factory()->create(['code' => 'parc-01', 'name' => 'Borne du parc']);
		$this->lea = User::factory()->create(['pseudo' => 'Lea']);
		$this->display = $this->mock(TerminalDisplay::class);
		$this->display->shouldReceive('show')->byDefault();
		Sanctum::actingAs($this->lea);
	}

	private function send(?Terminal $terminal = null, ?string $scannedAt = null)
	{
		$token = app(TerminalTokenService::class)->generate($terminal ?? $this->terminal, $scannedAt === null ? now() : now()->parse($scannedAt));

		return $this->postJson('/api/me/passages', ['token' => $token]);
	}

	private function accessory(string $code): Accessory
	{
		return Accessory::where('code', $code)->sole();
	}

	private function weekend(array $attributes = []): Event
	{
		return Event::factory()->between('2026-10-17 00:00:00', '2026-10-19 00:00:00')->create(['name' => 'Week-end au parc', ...$attributes]);
	}

	public function test_an_event_in_progress_adds_its_bonus_and_is_named_on_the_terminal(): void
	{
		$weekend = $this->weekend(['bonus_xp' => 50]);
		$this->display->shouldReceive('show')->once()->withArgs(fn (Terminal $terminal, array $display) => $terminal->is($this->terminal)
			&& $display === ['scanned_at' => '2026-10-17T15:00:00+02:00', 'status' => 'rewarded', 'message' => 'Bravo Lea ! +150 XP, bonus Week-end au parc', 'reward_xp' => 150]);

		$this->send()
			->assertOk()
			->assertJsonPath('status', 'rewarded')
			->assertJsonPath('reward_xp', 150)
			->assertJsonPath('message', 'Bravo Lea ! +150 XP, bonus Week-end au parc')
			->assertJsonPath('events', [['id' => $weekend->id, 'name' => 'Week-end au parc', 'bonus_xp' => 50, 'accessory' => null]]);

		$reward = EventPassage::sole();
		$this->assertTrue($reward->passage->is(Passage::sole()));
		$this->assertTrue($reward->event->is($weekend));
		$this->assertSame(50, $reward->bonus_xp);
	}

	public function test_without_an_event_the_answer_has_an_empty_list(): void
	{
		$this->send()->assertOk()->assertJsonPath('reward_xp', 100)->assertJsonPath('events', []);
	}

	public function test_an_event_only_applies_to_its_terminals(): void
	{
		$castle = Terminal::factory()->create();
		$weekend = $this->weekend(['all_terminals' => false]);
		$weekend->terminals()->attach($castle);

		$this->send()->assertOk()->assertJsonPath('reward_xp', 100)->assertJsonPath('events', []);
		$this->send($castle)->assertOk()->assertJsonPath('reward_xp', 150);

		$weekend->terminals()->attach($this->terminal);
		$this->travelTo('2026-10-18 10:00:00');

		$this->send()->assertOk()->assertJsonPath('reward_xp', 150);
	}

	public function test_the_period_is_read_at_the_time_of_the_scan(): void
	{
		Event::factory()->between('2026-10-17 14:00:00', '2026-10-17 16:00:00')->create();
		[$before, $start, $last, $end] = Terminal::factory()->count(4)->create();

		$this->send($before, '2026-10-17 13:59:59')->assertOk()->assertJsonPath('reward_xp', 100);
		$this->send($start, '2026-10-17 14:00:00')->assertOk()->assertJsonPath('reward_xp', 150);

		$this->travelTo('2026-10-17 18:00:00');

		$this->send($last, '2026-10-17 15:59:59')->assertOk()->assertJsonPath('reward_xp', 150);
		$this->send($end, '2026-10-17 16:00:00')->assertOk()->assertJsonPath('reward_xp', 100);
	}

	public function test_an_inactive_event_gives_nothing(): void
	{
		$this->weekend(['active' => false, 'accessory_id' => $this->accessory('crown')->id]);

		$this->send()->assertOk()->assertJsonPath('reward_xp', 100)->assertJsonPath('events', []);

		$this->assertSame(0, $this->lea->accessories()->count());
	}

	public function test_only_the_rewarded_passage_of_the_day_gets_the_bonus(): void
	{
		$this->weekend();
		$this->send()->assertOk();
		$this->travel(2)->hours();

		$this->send()->assertOk()->assertJsonPath('status', 'already_rewarded')->assertJsonPath('reward_xp', 0)->assertJsonPath('events', []);

		$this->assertSame([150, 0], Passage::orderBy('id')->pluck('reward_xp')->all());
		$this->assertSame(1, EventPassage::count());
	}

	public function test_an_event_gives_its_accessory_with_the_first_rewarded_passage_only(): void
	{
		$weekend = $this->weekend(['bonus_xp' => 50, 'accessory_id' => $this->accessory('crown')->id]);
		Companion::factory()->for($this->lea)->create();

		$this->send()
			->assertOk()
			->assertJsonPath('message', 'Bravo Lea ! +150 XP, bonus Week-end au parc, nouvel accessoire : Couronne')
			->assertJsonPath('events.0.accessory', ['id' => 'crown', 'label' => 'Couronne'])
			->assertJsonMissingPath('accessory')
			->assertJsonPath('companion.unlocked_accessories', ['crown']);

		$this->travelTo('2026-10-18 10:00:00');

		$this->send()
			->assertOk()
			->assertJsonPath('message', 'Bravo Lea ! +150 XP, bonus Week-end au parc')
			->assertJsonPath('events', [['id' => $weekend->id, 'name' => 'Week-end au parc', 'bonus_xp' => 50, 'accessory' => null]]);

		$this->assertSame(1, $this->lea->accessories()->count());
		$this->assertSame([$this->accessory('crown')->id, null], EventPassage::orderBy('id')->pluck('accessory_id')->all());
	}

	public function test_an_accessory_already_owned_or_withdrawn_is_skipped_but_the_bonus_stays(): void
	{
		$crown = $this->accessory('crown');
		$this->lea->accessories()->create(['accessory_id' => $crown->id, 'unlocked_at' => now()]);
		$this->weekend(['accessory_id' => $crown->id]);
		$medal = $this->accessory('medal');
		Event::factory()->create(['name' => 'Fête du sport', 'bonus_xp' => 20, 'accessory_id' => $medal->id]);
		$medal->update(['active' => false]);

		$this->send()
			->assertOk()
			->assertJsonPath('reward_xp', 170)
			->assertJsonPath('message', 'Bravo Lea ! +170 XP, bonus Week-end au parc et Fête du sport')
			->assertJsonPath('events.0.accessory', null)
			->assertJsonPath('events.1.accessory', null);

		$this->assertSame(1, $this->lea->accessories()->count());
	}

	public function test_an_event_that_has_nothing_left_to_give_is_not_mentioned(): void
	{
		$crown = $this->accessory('crown');
		$this->lea->accessories()->create(['accessory_id' => $crown->id, 'unlocked_at' => now()]);
		$this->weekend(['bonus_xp' => 0, 'accessory_id' => $crown->id]);

		$this->send()->assertOk()->assertJsonPath('message', 'Bravo Lea ! +100 XP')->assertJsonPath('events', []);

		$this->assertSame(0, EventPassage::count());
	}

	public function test_an_accessory_given_by_the_terminal_and_an_event_is_given_once(): void
	{
		$crown = $this->accessory('crown');
		$this->terminal->update(['accessory_id' => $crown->id]);
		$this->weekend(['bonus_xp' => 50, 'accessory_id' => $crown->id]);

		$this->send()
			->assertOk()
			->assertJsonPath('message', 'Bravo Lea ! +150 XP, bonus Week-end au parc, nouvel accessoire : Couronne')
			->assertJsonPath('accessory', ['id' => 'crown', 'label' => 'Couronne'])
			->assertJsonPath('events.0.accessory', null);

		$this->assertSame(1, $this->lea->accessories()->count());
		$this->assertSame($crown->id, Passage::sole()->accessory_id);
	}

	public function test_events_running_together_add_up(): void
	{
		$this->terminal->update(['accessory_id' => $this->accessory('diving_mask')->id]);
		Event::factory()->between('2026-10-17 12:00:00', '2026-10-17 18:00:00')->create(['name' => 'Fête du sport', 'bonus_xp' => 30, 'accessory_id' => $this->accessory('medal')->id]);
		$this->weekend(['bonus_xp' => 50, 'accessory_id' => $this->accessory('crown')->id]);
		Companion::factory()->for($this->lea)->create();

		$this->send()
			->assertOk()
			->assertJsonPath('reward_xp', 180)
			->assertJsonPath('message', 'Bravo Lea ! +180 XP, bonus Week-end au parc et Fête du sport, nouveaux accessoires : Masque de plongée, Couronne et Médaille')
			->assertJsonPath('events.0.name', 'Week-end au parc')
			->assertJsonPath('events.1.name', 'Fête du sport')
			->assertJsonPath('companion.xp', 180);

		$this->assertEqualsCanonicalizing(['diving_mask', 'crown', 'medal'], $this->lea->ownedAccessoryCodes());
	}

	public function test_a_refused_passage_gets_nothing_from_events(): void
	{
		$this->terminal->update(['place_id' => Place::factory()->create()->id]);
		$this->weekend(['accessory_id' => $this->accessory('crown')->id]);

		$this->send()->assertUnprocessable()->assertJsonValidationErrors('position');

		$this->assertSame(0, Passage::count());
		$this->assertSame(0, EventPassage::count());
		$this->assertSame(0, $this->lea->accessories()->count());
	}
}
