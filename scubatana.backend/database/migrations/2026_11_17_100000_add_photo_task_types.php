<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
	private const CODES = ['photo_place', 'photo_place_type'];

	public function up(): void
	{
		$now = now();

		DB::table('task_types')->insert(array_map(fn (array $type) => [...$type, 'app_action' => 'photo', 'params_schema' => null, 'active' => true, 'created_at' => $now, 'updated_at' => $now], [
			['code' => 'photo_place', 'label' => 'Prendre une photo dans un lieu'],
			['code' => 'photo_place_type', 'label' => 'Prendre une photo dans un type de lieu'],
		]));
	}

	public function down(): void
	{
		DB::table('task_types')->whereIn('code', self::CODES)->delete();
	}
};
