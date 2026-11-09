<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('reports', function (Blueprint $table) {
			$table->id();
			$table->foreignId('user_id')->constrained()->cascadeOnDelete();
			$table->foreignId('reported_id')->constrained('users')->cascadeOnDelete();
			$table->string('reason', 16);
			$table->string('comment', 500)->nullable();
			$table->string('status', 16)->default('pending');
			$table->timestamps();
			$table->index('status');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('reports');
	}
};
