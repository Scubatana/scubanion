<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('quests', function (Blueprint $table) {
			$table->id();
			$table->string('title', 80);
			$table->string('description')->nullable();
			$table->string('type', 32);
			$table->string('ambition', 32)->nullable();
			$table->unsignedInteger('goal')->default(1);
			$table->foreignId('place_id')->nullable()->constrained()->restrictOnDelete();
			$table->unsignedInteger('reward_xp')->default(0);
			$table->timestamp('starts_at')->nullable();
			$table->timestamp('ends_at')->nullable();
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('quests');
	}
};
