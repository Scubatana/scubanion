<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('place_types', function (Blueprint $table) {
			$table->id();
			$table->string('code', 32)->unique();
			$table->string('label', 80);
			$table->string('icon', 64)->nullable();
			$table->string('color', 16)->nullable();
			$table->boolean('on_map')->default(true);
			$table->boolean('active')->default(true);
			$table->timestamps();
		});

		$now = now();

		DB::table('place_types')->insert(array_map(fn (array $type) => [...$type, 'active' => true, 'created_at' => $now, 'updated_at' => $now], [
			['code' => 'park', 'label' => 'Parc', 'icon' => 'tree', 'color' => '#4FA871', 'on_map' => true],
			['code' => 'point_of_interest', 'label' => 'Point d\'intérêt', 'icon' => 'landmark', 'color' => '#2F6B7A', 'on_map' => true],
			['code' => 'partner', 'label' => 'Partenaire', 'icon' => 'store', 'color' => '#F0855A', 'on_map' => true],
			['code' => 'bus_stop', 'label' => 'Arrêt de bus', 'icon' => 'bus', 'color' => '#7C63C9', 'on_map' => true],
			['code' => 'zone', 'label' => 'Zone de ciblage', 'icon' => null, 'color' => null, 'on_map' => false],
		]));
	}

	public function down(): void
	{
		Schema::dropIfExists('place_types');
	}
};
