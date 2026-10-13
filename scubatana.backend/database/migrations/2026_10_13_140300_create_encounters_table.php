<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('encounters', function (Blueprint $table) {
			$table->id();
			$table->foreignId('user_id')->constrained()->cascadeOnDelete();
			$table->foreignId('other_user_id')->constrained('users')->cascadeOnDelete();
			$table->timestamp('met_at');
			$table->string('zone', 12)->nullable();
			$table->timestamp('validated_at')->nullable();
			$table->timestamps();
			$table->index(['user_id', 'other_user_id', 'met_at']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('encounters');
	}
};
