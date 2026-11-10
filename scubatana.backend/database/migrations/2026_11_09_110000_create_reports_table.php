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
			// Un signalement survit à la suppression d'un des deux comptes : l'id passe à null,
			// et le pseudo du joueur signalé, copié au moment du signalement, garde la ligne exploitable.
			$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
			$table->foreignId('reported_id')->nullable()->constrained('users')->nullOnDelete();
			$table->string('reported_pseudo', 20)->nullable();
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
