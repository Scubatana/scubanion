<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('companions', function (Blueprint $table) {
			$table->id();
			$table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
			$table->string('name', 32);
			$table->string('species', 32);
			$table->unsignedInteger('level')->default(1);
			$table->unsignedInteger('xp')->default(0);
			$table->unsignedTinyInteger('mood')->default(100);
			$table->unsignedTinyInteger('health')->default(100);
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('companions');
	}
};
