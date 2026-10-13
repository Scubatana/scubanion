<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('user_quests', function (Blueprint $table) {
			$table->id();
			$table->foreignId('user_id')->constrained()->cascadeOnDelete();
			$table->foreignId('quest_id')->constrained()->restrictOnDelete();
			$table->string('period', 16);
			$table->unsignedSmallInteger('current_step')->default(1);
			$table->string('source', 16)->default('rule');
			$table->timestamp('assigned_at');
			$table->timestamp('expires_at')->nullable();
			$table->timestamp('completed_at')->nullable();
			$table->timestamp('failed_at')->nullable();
			$table->unsignedInteger('reward_xp')->default(0);
			$table->timestamps();
			$table->unique(['user_id', 'quest_id', 'period']);
		});

		Schema::create('user_quest_tasks', function (Blueprint $table) {
			$table->id();
			$table->foreignId('user_quest_id')->constrained()->cascadeOnDelete();
			$table->foreignId('quest_task_id')->constrained()->restrictOnDelete();
			$table->unsignedInteger('value')->default(0);
			$table->unsignedInteger('baseline')->default(0);
			$table->timestamp('completed_at')->nullable();
			$table->timestamps();
			$table->unique(['user_quest_id', 'quest_task_id']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('user_quest_tasks');
		Schema::dropIfExists('user_quests');
	}
};
