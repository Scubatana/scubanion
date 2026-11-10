<?php

namespace Tests\Feature\Bornes;

use App\Models\Place;
use App\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
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

	public function test_it_adds_the_mqtt_account_of_the_terminal(): void
	{
		$directory = storage_path('framework/testing/mqtt');
		config(['bornes.mqtt.username' => 'scubanion.api', 'bornes.mqtt.password' => 'mot-de-passe-api', 'bornes.mqtt.accounts_directory' => $directory]);

		$this->artisan('bornes:creer', ['code' => 'chateau-01', 'nom' => 'Borne du Château'])
			->expectsOutputToContain('Compte MQTT de la borne ajouté')
			->assertSuccessful();

		$this->assertStringContainsString("\nchateau-01:", File::get("{$directory}/passwd"));
		File::deleteDirectory($directory);
	}

	public function test_without_mqtt_settings_the_terminal_is_still_created(): void
	{
		config(['bornes.mqtt.username' => null, 'bornes.mqtt.password' => null]);

		$this->artisan('bornes:creer', ['code' => 'chateau-01', 'nom' => 'Borne du Château'])
			->expectsOutputToContain('Compte MQTT non écrit')
			->assertSuccessful();

		$this->assertSame(1, Terminal::count());
	}

	public function test_a_terminal_cannot_take_the_name_of_the_api_account(): void
	{
		config(['bornes.mqtt.username' => 'scubanion-api']);

		$this->artisan('bornes:creer', ['code' => 'scubanion-api', 'nom' => 'Borne'])->assertFailed();

		$this->assertSame(0, Terminal::count());
	}

	public function test_it_refuses_a_bad_code_a_taken_code_and_an_unknown_place(): void
	{
		Terminal::factory()->create(['code' => 'chateau-01']);

		$this->artisan('bornes:creer', ['code' => 'Château 01', 'nom' => 'Borne'])->assertFailed();
		$this->artisan('bornes:creer', ['code' => 'parc-du-chateau-1', 'nom' => 'Borne'])->assertFailed();
		$this->artisan('bornes:creer', ['code' => 'chateau-01', 'nom' => 'Borne'])->assertFailed();
		$this->artisan('bornes:creer', ['code' => 'parc-01', 'nom' => 'Borne', '--lieu' => 999])->assertFailed();

		$this->assertSame(1, Terminal::count());
	}
}
