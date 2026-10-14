<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('activities', function (Blueprint $table) {
			$table->string('device_id', 64)->default('unknown')->after('day');
			$table->unsignedInteger('goal')->nullable()->after('steps');
			$table->unique(['user_id', 'day', 'device_id']);
		});

		Schema::table('activities', function (Blueprint $table) {
			$table->dropUnique(['user_id', 'day']);
		});
	}

	public function down(): void
	{
		$this->mergePhonesOfTheSameDay();

		Schema::table('activities', function (Blueprint $table) {
			$table->unique(['user_id', 'day']);
		});

		Schema::table('activities', function (Blueprint $table) {
			$table->dropUnique(['user_id', 'day', 'device_id']);
			$table->dropColumn(['device_id', 'goal']);
		});
	}

	private function mergePhonesOfTheSameDay(): void
	{
		$sharedDays = DB::table('activities')->select('user_id', 'day')->groupBy('user_id', 'day')->havingRaw('COUNT(*) > 1')->get();

		foreach ($sharedDays as $sharedDay) {
			$rows = DB::table('activities')->where('user_id', $sharedDay->user_id)->where('day', $sharedDay->day)->orderBy('id')->get();

			DB::table('activities')->where('id', $rows->first()->id)->update([
				'steps' => min((int) $rows->sum('steps'), config('steps.max_per_day')),
				'updated_at' => now(),
			]);
			DB::table('activities')->whereIn('id', $rows->skip(1)->pluck('id'))->delete();
		}
	}
};
