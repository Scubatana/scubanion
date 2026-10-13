<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('passages', function (Blueprint $table) {
			$table->id();
			$table->foreignId('terminal_id')->constrained()->cascadeOnDelete();
			$table->foreignId('user_id')->constrained()->cascadeOnDelete();
			$table->string('client_id', 64);
			$table->timestamp('scanned_at');
			$table->unsignedInteger('reward_xp')->default(0);
			$table->timestamps();
			$table->unique(['terminal_id', 'client_id']);
			$table->index(['user_id', 'scanned_at']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('passages');
	}
};
