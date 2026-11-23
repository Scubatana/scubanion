<?php

namespace Tests\Feature\Encounters;

use App\Models\Encounter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForgetEncounterZonesTest extends TestCase
{
	use RefreshDatabase;

	public function test_zones_older_than_thirty_days_are_forgotten_and_the_encounter_kept(): void
	{
		$this->travelTo('2026-11-17 03:00:00');
		$old = Encounter::factory()->create(['met_at' => now()->subDays(31), 'validated_at' => now()->subDays(31)]);
		$recent = Encounter::factory()->create(['met_at' => now()->subDays(29), 'validated_at' => now()->subDays(29)]);

		$this->artisan('rencontres:oublier-zones')
			->expectsOutput('1 zone(s) de rencontre effacée(s).')
			->assertSuccessful();

		$this->assertNull($old->fresh()->zone);
		$this->assertTrue($old->fresh()->isValidated());
		$this->assertSame('spv2bc', $recent->fresh()->zone);
	}

	public function test_it_runs_every_day(): void
	{
		$events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains($event->command ?? '', 'rencontres:oublier-zones'));

		$this->assertCount(1, $events);
		$this->assertSame('0 0 * * *', $events->first()->expression);
	}
}
