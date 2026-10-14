<?php

namespace Tests\Feature\Me;

use App\Models\Accessory;
use App\Models\Companion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateCompanionTest extends TestCase
{
	use RefreshDatabase;

	private function look(array $overrides = []): array
	{
		return array_merge([
			'name' => 'Bulle',
			'species' => 'dog',
			'color' => 'coral',
			'accessories' => ['tophat', 'handlebar'],
		], $overrides);
	}

	private function actingAsOwner(): Companion
	{
		$user = User::factory()->create();
		Sanctum::actingAs($user);

		return Companion::factory()->for($user)->create(['level' => 3, 'xp' => 120]);
	}

	public function test_it_requires_a_token(): void
	{
		$this->putJson('/api/me/companion', $this->look())->assertUnauthorized();
	}

	public function test_it_saves_the_look_and_keeps_the_progress(): void
	{
		$companion = $this->actingAsOwner();

		$this->putJson('/api/me/companion', $this->look())
			->assertOk()
			->assertJsonPath('companion.name', 'Bulle')
			->assertJsonPath('companion.species', 'dog')
			->assertJsonPath('companion.color', 'coral')
			->assertJsonPath('companion.accessories', ['tophat', 'handlebar'])
			->assertJsonPath('companion.level', 3)
			->assertJsonPath('companion.xp', 120);

		$this->assertSame(['tophat', 'handlebar'], $companion->refresh()->accessories);
		$this->getJson('/api/me')->assertJsonPath('companion.color', 'coral')->assertJsonPath('companion.accessories', ['tophat', 'handlebar']);
	}

	public function test_a_new_companion_has_the_default_look(): void
	{
		Sanctum::actingAs(User::factory()->withoutProfile()->create());

		$this->putJson('/api/me/profile', ['pseudo' => 'Lea', 'birth_date' => '2004-05-12', 'ambition' => 'fitness'])
			->assertOk()
			->assertJsonPath('companion.species', 'scuba')
			->assertJsonPath('companion.color', 'ocean')
			->assertJsonPath('companion.accessories', []);
	}

	public function test_it_only_changes_the_companion_of_the_current_player(): void
	{
		$other = Companion::factory()->create(['name' => 'Intact']);
		$this->actingAsOwner();

		$this->putJson('/api/me/companion', $this->look())->assertOk();

		$this->assertSame('Intact', $other->refresh()->name);
		$this->assertSame('ocean', $other->color);
	}

	public function test_it_rejects_two_accessories_of_the_same_category(): void
	{
		$this->actingAsOwner();

		$this->putJson('/api/me/companion', $this->look(['accessories' => ['tophat', 'crown']]))
			->assertUnprocessable()
			->assertJsonValidationErrors('accessories');
	}

	public function test_an_accessory_already_worn_stays_wearable_once_locked_or_retired(): void
	{
		$companion = $this->actingAsOwner();
		$companion->update(['accessories' => ['crown', 'scarf']]);
		Accessory::where('code', 'scarf')->update(['active' => false]);

		$this->putJson('/api/me/companion', $this->look(['accessories' => ['crown', 'scarf']]))->assertOk();
		$this->putJson('/api/me/companion', $this->look(['accessories' => ['crown']]))->assertOk();
		$this->putJson('/api/me/companion', $this->look(['accessories' => ['crown', 'scarf']]))->assertUnprocessable();
		$this->putJson('/api/me/companion', $this->look(['accessories' => ['medal']]))->assertUnprocessable()->assertJsonValidationErrors('accessories');
	}

	public function test_it_rejects_invalid_values(): void
	{
		$this->actingAsOwner();

		$this->putJson('/api/me/companion', $this->look(['species' => 'dragon']))->assertUnprocessable()->assertJsonValidationErrors('species');
		$this->putJson('/api/me/companion', $this->look(['color' => 'invisible']))->assertUnprocessable()->assertJsonValidationErrors('color');
		$this->putJson('/api/me/companion', $this->look(['accessories' => ['jetpack']]))->assertUnprocessable()->assertJsonValidationErrors('accessories.0');
		$this->putJson('/api/me/companion', $this->look(['accessories' => ['tophat', 'tophat']]))->assertUnprocessable();
		$this->putJson('/api/me/companion', $this->look(['accessories' => 'tophat']))->assertUnprocessable()->assertJsonValidationErrors('accessories');
		$this->putJson('/api/me/companion', $this->look(['name' => ' a ']))->assertUnprocessable()->assertJsonValidationErrors('name');
		$this->putJson('/api/me/companion', $this->look(['name' => str_repeat('a', 21)]))->assertUnprocessable()->assertJsonValidationErrors('name');
		$this->putJson('/api/me/companion', [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'species', 'color', 'accessories']);
	}

	public function test_it_refuses_a_player_without_companion(): void
	{
		Sanctum::actingAs(User::factory()->withoutProfile()->create());

		$this->putJson('/api/me/companion', $this->look())->assertConflict();
	}

	public function test_the_catalog_route_returns_the_four_sections(): void
	{
		$this->getJson('/api/companion/catalog')->assertUnauthorized();

		$this->actingAsOwner();

		$this->getJson('/api/companion/catalog')
			->assertOk()
			->assertJsonStructure([
				'categories' => [['id', 'label', 'origin' => ['x', 'y']]],
				'species' => [['id', 'label', 'svg', 'anchors' => ['hat' => ['x', 'y', 'width']]]],
				'colors' => [['id', 'label', 'main', 'shade', 'light']],
				'accessories' => [['id', 'label', 'category', 'image']],
			]);
	}
}
