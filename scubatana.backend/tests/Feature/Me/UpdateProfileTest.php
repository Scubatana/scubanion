<?php

namespace Tests\Feature\Me;

use App\Models\Companion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateProfileTest extends TestCase
{
	use RefreshDatabase;

	private function profile(array $overrides = []): array
	{
		return array_merge([
			'pseudo' => 'MarcheurDuSoir',
			'birth_date' => '2004-05-12',
			'gender' => 'female',
			'ambition' => 'fitness',
		], $overrides);
	}

	public function test_it_requires_a_token(): void
	{
		$this->putJson('/api/me/profile', $this->profile())->assertUnauthorized();
	}

	public function test_it_saves_the_profile_and_creates_the_companion(): void
	{
		$user = User::factory()->withoutProfile()->create();
		Sanctum::actingAs($user);

		$this->putJson('/api/me/profile', $this->profile())
			->assertOk()
			->assertJsonPath('player.pseudo', 'MarcheurDuSoir')
			->assertJsonPath('player.birth_date', '2004-05-12')
			->assertJsonPath('player.gender', 'female')
			->assertJsonPath('player.ambition', 'fitness')
			->assertJsonPath('player.profile_completed', true)
			->assertJsonPath('companion.level', 1)
			->assertJsonPath('companion.xp', 0);

		$this->assertSame(1, Companion::where('user_id', $user->id)->count());
	}

	public function test_the_gender_is_optional(): void
	{
		Sanctum::actingAs(User::factory()->withoutProfile()->create());

		$this->putJson('/api/me/profile', $this->profile(['gender' => null]))
			->assertOk()
			->assertJsonPath('player.gender', null);
	}

	public function test_saving_again_keeps_the_companion_progress(): void
	{
		$user = User::factory()->create();
		Companion::factory()->for($user)->create(['level' => 3, 'xp' => 120]);
		Sanctum::actingAs($user);

		$this->putJson('/api/me/profile', $this->profile(['pseudo' => 'NouveauPseudo']))
			->assertOk()
			->assertJsonPath('player.pseudo', 'NouveauPseudo')
			->assertJsonPath('companion.level', 3)
			->assertJsonPath('companion.xp', 120);

		$this->assertSame(1, Companion::count());
	}

	public function test_it_rejects_a_pseudo_already_taken(): void
	{
		User::factory()->create(['pseudo' => 'MarcheurDuSoir']);
		$user = User::factory()->create(['pseudo' => 'Lea']);
		Sanctum::actingAs($user);

		$this->putJson('/api/me/profile', $this->profile())->assertUnprocessable()->assertJsonValidationErrors('pseudo');
		$this->putJson('/api/me/profile', $this->profile(['pseudo' => 'Lea']))->assertOk();
	}

	public function test_it_rejects_a_pseudo_too_short_or_too_long(): void
	{
		Sanctum::actingAs(User::factory()->withoutProfile()->create());

		$this->putJson('/api/me/profile', $this->profile(['pseudo' => ' ab ']))->assertUnprocessable()->assertJsonValidationErrors('pseudo');
		$this->putJson('/api/me/profile', $this->profile(['pseudo' => str_repeat('a', 21)]))->assertUnprocessable()->assertJsonValidationErrors('pseudo');
	}

	public function test_it_enforces_the_minimum_age(): void
	{
		Sanctum::actingAs(User::factory()->withoutProfile()->create());

		$this->putJson('/api/me/profile', $this->profile(['birth_date' => now()->subYears(15)->addDay()->toDateString()]))
			->assertUnprocessable()
			->assertJsonValidationErrors('birth_date');
		$this->putJson('/api/me/profile', $this->profile(['birth_date' => now()->subYears(15)->toDateString()]))->assertOk();
	}

	public function test_it_rejects_invalid_values(): void
	{
		Sanctum::actingAs(User::factory()->withoutProfile()->create());

		$this->putJson('/api/me/profile', $this->profile(['birth_date' => '12/05/2004']))->assertUnprocessable()->assertJsonValidationErrors('birth_date');
		$this->putJson('/api/me/profile', $this->profile(['ambition' => 'dominer_le_monde']))->assertUnprocessable()->assertJsonValidationErrors('ambition');
		$this->putJson('/api/me/profile', $this->profile(['gender' => 'robot']))->assertUnprocessable()->assertJsonValidationErrors('gender');
		$this->putJson('/api/me/profile', [])->assertUnprocessable()->assertJsonValidationErrors(['pseudo', 'birth_date', 'ambition']);
	}
}
