<?php

namespace Tests\Feature\Steps;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DevicesMigrationTest extends TestCase
{
	use RefreshDatabase;

	private function migration(): object
	{
		return require database_path('migrations/2026_10_13_101248_add_device_and_goal_to_activities_table.php');
	}

	public function test_rolling_back_adds_up_the_phones_of_each_day_into_one_line(): void
	{
		$lea = User::factory()->create();
		$yanis = User::factory()->create();
		Activity::factory()->for($lea)->create(['day' => '2026-10-13', 'device_id' => 'phone-aaaa-1111', 'steps' => 3000]);
		Activity::factory()->for($lea)->create(['day' => '2026-10-13', 'device_id' => 'phone-bbbb-2222', 'steps' => 1500]);
		Activity::factory()->for($lea)->create(['day' => '2026-10-12', 'device_id' => 'phone-aaaa-1111', 'steps' => 800]);
		Activity::factory()->for($yanis)->create(['day' => '2026-10-13', 'device_id' => 'phone-cccc-3333', 'steps' => 98000]);
		Activity::factory()->for($yanis)->create(['day' => '2026-10-13', 'device_id' => 'phone-dddd-4444', 'steps' => 5000]);

		$this->migration()->down();

		$this->assertFalse(Schema::hasColumn('activities', 'device_id'));
		$this->assertSame(3, DB::table('activities')->count());
		$this->assertSame(4500, (int) DB::table('activities')->where('user_id', $lea->id)->whereDate('day', '2026-10-13')->value('steps'));
		$this->assertSame(800, (int) DB::table('activities')->where('user_id', $lea->id)->whereDate('day', '2026-10-12')->value('steps'));
		$this->assertSame(100000, (int) DB::table('activities')->where('user_id', $yanis->id)->value('steps'));

		$this->expectException(UniqueConstraintViolationException::class);
		$storedDay = DB::table('activities')->where('user_id', $lea->id)->whereDate('day', '2026-10-12')->value('day');
		DB::table('activities')->insert(['user_id' => $lea->id, 'day' => $storedDay, 'steps' => 1, 'source' => 'phone']);
	}

	public function test_the_migration_can_be_applied_again_after_a_rollback(): void
	{
		Activity::factory()->for(User::factory()->create())->create(['day' => '2026-10-13', 'device_id' => 'phone-aaaa-1111', 'steps' => 3000]);

		$this->migration()->down();
		$this->migration()->up();

		$this->assertTrue(Schema::hasColumn('activities', 'device_id'));
		$this->assertSame('unknown', DB::table('activities')->value('device_id'));
	}
}
