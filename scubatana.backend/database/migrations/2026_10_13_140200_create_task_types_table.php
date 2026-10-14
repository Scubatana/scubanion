<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('task_types', function (Blueprint $table) {
			$table->id();
			$table->string('code', 32)->unique();
			$table->string('label', 80);
			$table->string('app_action', 16);
			$table->json('params_schema')->nullable();
			$table->boolean('active')->default(true);
			$table->timestamps();
		});

		$now = now();

		DB::table('task_types')->insert(array_map(fn (array $type) => [...$type, 'active' => true, 'created_at' => $now, 'updated_at' => $now], [
			['code' => 'steps', 'label' => 'Marcher', 'app_action' => 'auto', 'params_schema' => null],
			['code' => 'visit_place', 'label' => 'Aller à un lieu', 'app_action' => 'checkin', 'params_schema' => null],
			['code' => 'visit_place_type', 'label' => 'Aller à un type de lieu', 'app_action' => 'checkin', 'params_schema' => null],
			['code' => 'scan_code', 'label' => 'Scanner un code', 'app_action' => 'scan', 'params_schema' => json_encode(['code' => 'string'])],
			['code' => 'quiz', 'label' => 'Répondre à un quiz', 'app_action' => 'answer', 'params_schema' => json_encode(['questions' => [['question' => 'string', 'choices' => ['string'], 'answer' => 'integer']]])],
			['code' => 'meet_player', 'label' => 'Croiser un joueur', 'app_action' => 'meet', 'params_schema' => null],
		]));
	}

	public function down(): void
	{
		Schema::dropIfExists('task_types');
	}
};
