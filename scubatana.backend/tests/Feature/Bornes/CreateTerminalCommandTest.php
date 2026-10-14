<?php

namespace Tests\Feature\Bornes;

use App\Models\Place;
use App\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTerminalCommandTest extends TestCase
{
	use RefreshDatabase;

	public function test_it_declares_a_terminal_at_a_place(): void
	{
		$place = Place::factory()->create();

		$this->artisan('bornes:creer', ['code' => 'chateau-01', 'nom' => 'Borne du Château', '--lieu' => $place->id])
			->expectsOutputToContain('Secret à enregistrer dans la borne')
			->assertSuccessful();

		$terminal = Terminal::sole();
		$this->assertSame(43, strlen($terminal->secret));
		$this->assertSame('chateau-01', $terminal->code);
		$this->assertTrue($terminal->place->is($place));
	}

	public function test_it_refuses_a_bad_code_a_taken_code_and_an_unknown_place(): void
	{
		Terminal::factory()->create(['code' => 'chateau-01']);

		$this->artisan('bornes:creer', ['code' => 'Château 01', 'nom' => 'Borne'])->assertFailed();
		$this->artisan('bornes:creer', ['code' => 'chateau-01', 'nom' => 'Borne'])->assertFailed();
		$this->artisan('bornes:creer', ['code' => 'parc-01', 'nom' => 'Borne', '--lieu' => 999])->assertFailed();

		$this->assertSame(1, Terminal::count());
	}
}
