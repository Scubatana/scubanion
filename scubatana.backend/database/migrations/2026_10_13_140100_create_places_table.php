<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('places', function (Blueprint $table) {
			$table->id();
			$table->foreignId('place_type_id')->constrained()->restrictOnDelete();
			$table->string('name', 120);
			$table->decimal('latitude', 9, 6);
			$table->decimal('longitude', 9, 6);
			$table->unsignedInteger('radius_m')->nullable();
			$table->decimal('min_lat', 9, 6);
			$table->decimal('max_lat', 9, 6);
			$table->decimal('min_lng', 9, 6);
			$table->decimal('max_lng', 9, 6);
			$table->json('attributes')->nullable();
			$table->string('source', 32)->default('manual');
			$table->string('external_id', 64)->nullable();
			$table->timestamp('synced_at')->nullable();
			$table->boolean('active')->default(true);
			$table->timestamps();
			$table->index(['min_lat', 'max_lat', 'min_lng', 'max_lng']);
			$table->unique(['source', 'external_id']);
		});

		Schema::create('place_points', function (Blueprint $table) {
			$table->id();
			$table->foreignId('place_id')->constrained()->cascadeOnDelete();
			$table->unsignedSmallInteger('position');
			$table->decimal('latitude', 9, 6);
			$table->decimal('longitude', 9, 6);
			$table->unique(['place_id', 'position']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('place_points');
		Schema::dropIfExists('places');
	}
};
