<?php

namespace Tests\Feature\Bornes;

use App\Models\Event;
use App\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateEventCommandTest extends TestCase
{
	use RefreshDatabase;

	public function test_it_creates_a_weekend_bonus_on_some_terminals(): void
	{
		$park = Terminal::factory()->create(['code' => 'parc-01']);
		$castle = Terminal::factory()->create(['code' => 'chateau-01']);
		Terminal::factory()->create(['code' => 'port-01']);

		$this->artisan('evenements:creer', ['nom' => 'Week-end au parc', 'debut' => '2026-10-17 00:00', 'fin' => '2026-10-19 00:00', '--bonus' => 50, '--borne' => ['parc-01', 'chateau-01'], '--description' => 'Double XP au parc'])
			->expectsOutputToContain('), du 17/10/2026 00:00 au 19/10/2026 00:00 : parc-01, chateau-01.')
			->assertSuccessful();

		$event = Event::sole();
		$this->assertSame('Week-end au parc', $event->name);
		$this->assertSame('2026-10-17 00:00:00', $event->starts_at->toDateTimeString());
		$this->assertSame('2026-10-19 00:00:00', $event->ends_at->toDateTimeString());
		$this->assertSame(50, $event->bonus_xp);
		$this->assertNull($event->accessory_id);
		$this->assertFalse($event->all_terminals);
		$this->assertSame('Double XP au parc', $event->description);
		$this->assertEqualsCanonicalizing([$park->id, $castle->id], $event->terminals->modelKeys());
	}

	public function test_it_creates_a_seasonal_accessory_on_every_terminal(): void
	{
		$this->artisan('evenements:creer', ['nom' => 'Halloween', 'debut' => '2026-10-31', 'fin' => '2026-11-02', '--accessoire' => 'crown', '--toutes-les-bornes' => true])
			->expectsOutputToContain(': toutes les bornes.')
			->assertSuccessful();

		$event = Event::sole();
		$this->assertSame('crown', $event->accessory->code);
		$this->assertSame(0, $event->bonus_xp);
		$this->assertTrue($event->all_terminals);
		$this->assertSame(0, $event->terminals()->count());
	}

	public function test_it_refuses_an_event_that_gives_nothing(): void
	{
		$this->artisan('evenements:creer', ['nom' => 'Rien', 'debut' => '2026-10-17', 'fin' => '2026-10-19', '--toutes-les-bornes' => true])
			->expectsOutputToContain('Un événement doit donner un bonus d’XP')
			->assertFailed();
		$this->artisan('evenements:creer', ['nom' => 'Rien', 'debut' => '2026-10-17', 'fin' => '2026-10-19', '--bonus' => 0, '--toutes-les-bornes' => true])->assertFailed();

		$this->assertSame(0, Event::count());
	}

	public function test_it_needs_the_terminals_or_all_of_them_but_not_both(): void
	{
		Terminal::factory()->create(['code' => 'parc-01']);

		$this->artisan('evenements:creer', ['nom' => 'Week-end', 'debut' => '2026-10-17', 'fin' => '2026-10-19', '--bonus' => 50])
			->expectsOutputToContain('--toutes-les-bornes')
			->assertFailed();
		$this->artisan('evenements:creer', ['nom' => 'Week-end', 'debut' => '2026-10-17', 'fin' => '2026-10-19', '--bonus' => 50, '--borne' => ['parc-01'], '--toutes-les-bornes' => true])
			->expectsOutputToContain('pas les deux')
			->assertFailed();
		$this->artisan('evenements:creer', ['nom' => 'Week-end', 'debut' => '2026-10-17', 'fin' => '2026-10-19', '--bonus' => 50, '--borne' => ['inconnue']])->assertFailed();

		$this->assertSame(0, Event::count());
	}

	public function test_it_refuses_bad_dates_a_long_name_and_an_accessory_that_cannot_be_earned(): void
	{
		$valid = ['nom' => 'Week-end', 'debut' => '2026-10-17', 'fin' => '2026-10-19', '--bonus' => 50, '--toutes-les-bornes' => true];

		$this->artisan('evenements:creer', [...$valid, 'fin' => '2026-10-17'])->assertFailed();
		$this->artisan('evenements:creer', [...$valid, 'debut' => 'demain matin'])->assertFailed();
		$this->artisan('evenements:creer', [...$valid, 'nom' => str_repeat('a', 41)])->assertFailed();
		$this->artisan('evenements:creer', [...$valid, '--accessoire' => 'tophat'])->assertFailed();
		$this->artisan('evenements:creer', [...$valid, '--accessoire' => 'inconnu'])->assertFailed();

		$this->assertSame(0, Event::count());
	}
}
