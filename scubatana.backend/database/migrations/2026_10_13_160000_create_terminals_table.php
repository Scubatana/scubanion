<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('terminals', function (Blueprint $table) {
			$table->id();
			$table->string('code', 32)->unique();
			$table->string('name', 80);
			$table->foreignId('place_id')->nullable()->constrained()->nullOnDelete();
			$table->string('status', 16)->default('offline');
			$table->string('firmware', 32)->nullable();
			$table->timestamp('last_seen_at')->nullable();
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('terminals');
	}
};
