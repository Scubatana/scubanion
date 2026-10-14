<?php

namespace Tests\Feature\Me;

use App\Models\Activity;
use App\Models\Companion;
use App\Models\LoginCode;
use App\Models\User;
use App\Services\LoginCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
	use RefreshDatabase;

	public function test_it_requires_a_token(): void
	{
		$this->deleteJson('/api/me', ['confirm' => true])->assertUnauthorized();
	}

	public function test_it_asks_for_an_explicit_confirmation(): void
	{
		$user = User::factory()->create();
		Sanctum::actingAs($user);

		$this->deleteJson('/api/me')->assertUnprocessable()->assertJsonValidationErrors('confirm');
		$this->deleteJson('/api/me', ['confirm' => false])->assertUnprocessable()->assertJsonValidationErrors('confirm');

		$this->assertModelExists($user);
	}

	public function test_it_deletes_the_player_and_everything_attached_to_them(): void
	{
		$user = User::factory()->create(['email' => 'lea@example.com']);
		Companion::factory()->for($user)->create();
		Activity::factory()->for($user)->create(['steps' => 3000]);
		app(LoginCodeService::class)->issue('lea@example.com');
		$token = $user->createToken('mobile')->plainTextToken;
		$other = User::factory()->create();
		Companion::factory()->for($other)->create();
		Activity::factory()->for($other)->create();
		$other->createToken('mobile');

		$this->withToken($token)->deleteJson('/api/me?confirm=1')->assertNoContent();

		$this->assertModelMissing($user);
		$this->assertSame(0, Companion::where('user_id', $user->id)->count());
		$this->assertSame(0, Activity::where('user_id', $user->id)->count());
		$this->assertSame(0, LoginCode::where('email', 'lea@example.com')->count());
		$this->assertSame(1, PersonalAccessToken::count());
		$this->assertModelExists($other);
		$this->assertSame(1, Companion::count());
		$this->assertSame(1, Activity::count());

		Auth::forgetGuards();
		$this->withToken($token)->getJson('/api/me')->assertUnauthorized();
	}
}
