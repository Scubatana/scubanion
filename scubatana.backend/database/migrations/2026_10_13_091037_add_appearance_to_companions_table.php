<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('companions', function (Blueprint $table) {
			$table->string('color', 32)->default('ocean')->after('species');
			$table->json('accessories')->nullable()->after('color');
		});

		DB::table('companions')->update(['species' => 'scuba']);
	}

	public function down(): void
	{
		Schema::table('companions', function (Blueprint $table) {
			$table->dropColumn(['color', 'accessories']);
		});
	}
};
