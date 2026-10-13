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
			$table->string('name', 120);
			$table->text('description')->nullable();
			$table->string('icon', 64)->nullable();
			$table->unsignedInteger('reward_xp')->default(0);
			$table->string('reward_accessory', 32)->nullable();
			$table->boolean('auto_assign')->default(false);
			$table->string('target_ambition', 32)->nullable();
			$table->foreignId('target_zone_id')->nullable()->constrained('places')->restrictOnDelete();
			$table->string('repeat', 16)->default('once');
			$table->timestamp('starts_at')->nullable();
			$table->timestamp('ends_at')->nullable();
			$table->string('status', 16)->default('draft');
			$table->timestamps();
			$table->index(['status', 'auto_assign']);
		});

		Schema::create('quest_tasks', function (Blueprint $table) {
			$table->id();
			$table->foreignId('quest_id')->constrained()->cascadeOnDelete();
			$table->unsignedSmallInteger('step');
			$table->unsignedSmallInteger('position');
			$table->foreignId('task_type_id')->constrained()->restrictOnDelete();
			$table->string('description');
			$table->unsignedInteger('target')->default(1);
			$table->foreignId('place_id')->nullable()->constrained()->restrictOnDelete();
			$table->foreignId('place_type_id')->nullable()->constrained()->restrictOnDelete();
			$table->json('params')->nullable();
			$table->timestamps();
			$table->unique(['quest_id', 'step', 'position']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('quest_tasks');
		Schema::dropIfExists('quests');
	}
};
