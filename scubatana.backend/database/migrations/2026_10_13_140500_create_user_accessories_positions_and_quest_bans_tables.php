<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('user_accessories', function (Blueprint $table) {
			$table->id();
			$table->foreignId('user_id')->constrained()->cascadeOnDelete();
			$table->string('accessory', 32);
			$table->foreignId('quest_id')->nullable()->constrained()->restrictOnDelete();
			$table->timestamp('unlocked_at');
			$table->unique(['user_id', 'accessory']);
		});

		Schema::create('user_positions', function (Blueprint $table) {
			$table->id();
			$table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
			$table->decimal('latitude', 9, 6);
			$table->decimal('longitude', 9, 6);
			$table->unsignedInteger('accuracy_m')->nullable();
			$table->boolean('mocked')->default(false);
			$table->timestamp('recorded_at');
			$table->timestamps();
		});

		Schema::create('quest_bans', function (Blueprint $table) {
			$table->id();
			$table->foreignId('user_id')->constrained()->cascadeOnDelete();
			$table->string('reason', 32);
			$table->json('evidence')->nullable();
			$table->timestamp('starts_at');
			$table->timestamp('ends_at');
			$table->timestamps();
			$table->index(['user_id', 'ends_at']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('quest_bans');
		Schema::dropIfExists('user_positions');
		Schema::dropIfExists('user_accessories');
	}
};
