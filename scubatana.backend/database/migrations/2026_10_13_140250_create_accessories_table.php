<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('accessories', function (Blueprint $table) {
			$table->id();
			$table->string('code', 32)->unique();
			$table->string('label', 80);
			$table->string('category', 32);
			$table->text('image');
			$table->boolean('earnable')->default(false);
			$table->boolean('active')->default(true);
			$table->timestamps();
		});

		$now = now();

		DB::table('accessories')->insert(array_map(fn (array $accessory) => [
			'code' => $accessory[0],
			'label' => $accessory[1],
			'category' => $accessory[2],
			'image' => trim(file_get_contents(resource_path("companion/accessories/{$accessory[0]}.svg"))),
			'earnable' => $accessory[3] ?? false,
			'active' => true,
			'created_at' => $now,
			'updated_at' => $now,
		], [
			['tophat', 'Haut-de-forme', 'hat'],
			['crown', 'Couronne', 'hat', true],
			['cap', 'Casquette', 'hat'],
			['diving_mask', 'Masque de plongée', 'glasses', true],
			['sunglasses', 'Lunettes de soleil', 'glasses'],
			['round_glasses', 'Lunettes rondes', 'glasses'],
			['heart_glasses', 'Lunettes cœur', 'glasses'],
			['handlebar', 'Moustache guidon', 'moustache'],
			['pencil', 'Moustache fine', 'moustache'],
			['walrus', 'Moustache morse', 'moustache'],
			['snorkel', 'Tuba', 'mouth'],
			['lollipop', 'Sucette', 'mouth'],
			['flower', 'Fleur', 'mouth'],
			['bowtie', 'Nœud papillon', 'neck'],
			['scarf', 'Écharpe', 'neck'],
			['medal', 'Médaille', 'neck', true],
		]));
	}

	public function down(): void
	{
		Schema::dropIfExists('accessories');
	}
};
