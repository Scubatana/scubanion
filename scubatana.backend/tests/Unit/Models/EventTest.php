<?php

namespace Tests\Unit\Models;

use App\Models\Accessory;
use App\Models\Event;
use App\Models\EventPassage;
use App\Models\Passage;
use App\Models\Terminal;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class EventTest extends TestCase
{
	use RefreshDatabase;

	private function accessory(string $code): Accessory
	{
		return Accessory::where('code', $code)->sole();
	}

	public function test_an_event_must_end_after_it_starts(): void
	{
		$this->expectException(InvalidArgumentException::class);

		Event::factory()->between('2026-10-17 00:00:00', '2026-10-17 00:00:00')->create();
	}

	public function test_an_event_must_give_something(): void
	{
		$this->expectException(InvalidArgumentException::class);

		Event::factory()->create(['bonus_xp' => 0]);
	}

	public function test_an_event_only_gives_an_earnable_accessory(): void
	{
		$this->expectException(InvalidArgumentException::class);

		Event::factory()->create(['accessory_id' => $this->accessory('tophat')->id]);
	}

	public function test_an_event_can_give_only_an_accessory(): void
	{
		$event = Event::factory()->create(['bonus_xp' => 0, 'accessory_id' => $this->accessory('crown')->id]);

		$this->assertSame('crown', $event->accessory->code);
	}

	public function test_in_progress_at_includes_the_start_and_excludes_the_end(): void
	{
		$weekend = Event::factory()->between('2026-10-17 00:00:00', '2026-10-19 00:00:00')->create();
		Event::factory()->between('2026-10-17 00:00:00', '2026-10-19 00:00:00')->create(['active' => false]);
		$at = fn (string $moment) => Event::inProgressAt(now()->parse($moment))->pluck('id')->all();

		$this->assertSame([], $at('2026-10-16 23:59:59'));
		$this->assertSame([$weekend->id], $at('2026-10-17 00:00:00'));
		$this->assertSame([$weekend->id], $at('2026-10-18 23:59:59'));
		$this->assertSame([], $at('2026-10-19 00:00:00'));
		$this->assertTrue($weekend->isInProgressAt(now()->parse('2026-10-17 00:00:00')));
		$this->assertFalse($weekend->isInProgressAt(now()->parse('2026-10-19 00:00:00')));
	}

	public function test_not_finished_at_keeps_the_upcoming_and_running_active_events(): void
	{
		$this->travelTo('2026-10-18 12:00:00');
		$running = Event::factory()->between('2026-10-17 00:00:00', '2026-10-19 00:00:00')->create();
		$upcoming = Event::factory()->between('2026-10-24 00:00:00', '2026-10-26 00:00:00')->create();
		Event::factory()->between('2026-10-10 00:00:00', '2026-10-12 00:00:00')->create();
		Event::factory()->between('2026-10-24 00:00:00', '2026-10-26 00:00:00')->create(['active' => false]);

		$this->assertSame([$running->id, $upcoming->id], Event::notFinishedAt(now())->orderBy('id')->pluck('id')->all());
	}

	public function test_at_terminal_keeps_the_events_of_every_terminal_and_those_linked_to_it(): void
	{
		$castle = Terminal::factory()->create();
		$park = Terminal::factory()->create();
		$everywhere = Event::factory()->create(['all_terminals' => true]);
		$atTheCastle = Event::factory()->create(['all_terminals' => false]);
		$atTheCastle->terminals()->attach($castle);
		Event::factory()->create(['all_terminals' => false])->terminals()->attach($park);
		Event::factory()->create(['all_terminals' => false]);

		$this->assertSame([$everywhere->id, $atTheCastle->id], Event::atTerminal($castle)->orderBy('id')->pluck('id')->all());
	}

	public function test_an_event_that_rewarded_a_passage_cannot_be_deleted(): void
	{
		$event = Event::factory()->create();
		EventPassage::create(['passage_id' => Passage::factory()->create()->id, 'event_id' => $event->id, 'bonus_xp' => 50]);

		$this->expectException(QueryException::class);

		$event->delete();
	}

	public function test_an_unused_event_is_deleted_with_its_terminal_links(): void
	{
		$event = Event::factory()->create(['all_terminals' => false]);
		$event->terminals()->attach(Terminal::factory()->create());

		$event->delete();

		$this->assertDatabaseCount('event_terminal', 0);
	}

	public function test_the_accessory_of_an_event_cannot_be_deleted(): void
	{
		Event::factory()->create(['accessory_id' => $this->accessory('crown')->id]);

		$this->expectException(QueryException::class);

		$this->accessory('crown')->delete();
	}

	public function test_deleting_a_player_deletes_what_the_events_gave_to_their_passages(): void
	{
		$passage = Passage::factory()->create();
		EventPassage::create(['passage_id' => $passage->id, 'event_id' => Event::factory()->create()->id, 'bonus_xp' => 50]);

		$passage->user->delete();

		$this->assertDatabaseCount('event_passage', 0);
		$this->assertDatabaseCount('events', 1);
	}
}
