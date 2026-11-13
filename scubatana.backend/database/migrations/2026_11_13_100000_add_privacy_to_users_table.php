<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('users', function (Blueprint $table) {
			$table->boolean('share_steps')->default(false)->after('daily_step_goal');
			$table->boolean('share_streak')->default(false)->after('share_steps');
		});
	}

	public function down(): void
	{
		Schema::table('users', function (Blueprint $table) {
			$table->dropColumn(['share_steps', 'share_streak']);
		});
	}
};
