<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
		Schema::table('activities', function (Blueprint $table) {
			$table->unique(['user_id', 'day']);
		});

		Schema::table('activities', function (Blueprint $table) {
			$table->dropUnique(['user_id', 'day', 'device_id']);
			$table->dropColumn(['device_id', 'goal']);
		});
	}
};
