<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('terminals', function (Blueprint $table) {
			$table->foreignId('accessory_id')->nullable()->after('place_id')->constrained()->nullOnDelete();
		});

		Schema::table('passages', function (Blueprint $table) {
			$table->foreignId('accessory_id')->nullable()->after('reward_xp')->constrained()->nullOnDelete();
		});
	}

	public function down(): void
	{
		Schema::table('passages', function (Blueprint $table) {
			$table->dropConstrainedForeignId('accessory_id');
		});

		Schema::table('terminals', function (Blueprint $table) {
			$table->dropConstrainedForeignId('accessory_id');
		});
	}
};
