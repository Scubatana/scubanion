<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('users', function (Blueprint $table) {
			$table->id();
			$table->string('email')->unique();
			$table->string('pseudo', 20)->nullable()->unique();
			$table->date('birth_date')->nullable();
			$table->string('gender', 16)->nullable();
			$table->string('ambition', 32)->nullable();
			$table->unsignedInteger('daily_step_goal')->default(5000);
			$table->timestamp('terms_accepted_at')->nullable();
			$table->timestamps();
		});

		Schema::create('sessions', function (Blueprint $table) {
			$table->string('id')->primary();
			$table->foreignId('user_id')->nullable()->index();
			$table->string('ip_address', 45)->nullable();
			$table->text('user_agent')->nullable();
			$table->longText('payload');
			$table->integer('last_activity')->index();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('users');
		Schema::dropIfExists('sessions');
	}
};
