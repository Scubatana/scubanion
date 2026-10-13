<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('activities', function (Blueprint $table) {
			$table->id();
			$table->foreignId('user_id')->constrained()->cascadeOnDelete();
			$table->date('day');
			$table->unsignedInteger('steps')->default(0);
			$table->string('source', 32)->default('phone');
			$table->timestamps();
			$table->unique(['user_id', 'day']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('activities');
	}
};
