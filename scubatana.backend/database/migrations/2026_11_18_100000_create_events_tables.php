<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('events', function (Blueprint $table) {
			$table->id();
			$table->string('name', 40);
			$table->text('description')->nullable();
			$table->timestamp('starts_at');
			$table->timestamp('ends_at');
			$table->unsignedInteger('bonus_xp')->default(0);
			$table->foreignId('accessory_id')->nullable()->constrained()->restrictOnDelete();
			$table->boolean('all_terminals')->default(false);
			$table->boolean('active')->default(true);
			$table->timestamps();
			$table->index(['starts_at', 'ends_at']);
		});

		Schema::create('event_terminal', function (Blueprint $table) {
			$table->foreignId('event_id')->constrained()->cascadeOnDelete();
			$table->foreignId('terminal_id')->constrained()->cascadeOnDelete();
			$table->primary(['event_id', 'terminal_id']);
		});

		Schema::create('event_passage', function (Blueprint $table) {
			$table->id();
			$table->foreignId('passage_id')->constrained()->cascadeOnDelete();
			$table->foreignId('event_id')->constrained()->restrictOnDelete();
			$table->unsignedInteger('bonus_xp')->default(0);
			$table->foreignId('accessory_id')->nullable()->constrained()->nullOnDelete();
			$table->unique(['passage_id', 'event_id']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('event_passage');
		Schema::dropIfExists('event_terminal');
		Schema::dropIfExists('events');
	}
};
