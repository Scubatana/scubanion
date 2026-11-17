<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('cheers', function (Blueprint $table) {
			$table->id();
			$table->foreignId('user_id')->constrained()->cascadeOnDelete();
			$table->foreignId('friend_id')->constrained('users')->cascadeOnDelete();
			$table->date('day');
			$table->timestamps();
			// Un coup de patte par jour et par ami, même si deux requêtes arrivent en même temps.
			$table->unique(['user_id', 'friend_id', 'day']);
			$table->index(['friend_id', 'day']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('cheers');
	}
};
