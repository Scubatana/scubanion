<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
	private const ACCESSORIES = [
		['paw_scarf', "Foulard de l'amitié", 'neck'],
		['paw_bucket_hat', 'Bob à pattes', 'hat'],
		['starfish_glasses', 'Lunettes étoiles de mer', 'glasses'],
	];

	public function up(): void
	{
		$now = now();

		DB::table('accessories')->insert(array_map(fn (array $accessory) => [
			'code' => $accessory[0],
			'label' => $accessory[1],
			'category' => $accessory[2],
			'image' => trim(file_get_contents(resource_path("companion/accessories/{$accessory[0]}.svg"))),
			'earnable' => true,
			'active' => true,
			'created_at' => $now,
			'updated_at' => $now,
		], self::ACCESSORIES));
	}

	public function down(): void
	{
		$ids = DB::table('accessories')->whereIn('code', array_column(self::ACCESSORIES, 0))->pluck('id');

		DB::table('user_accessories')->whereIn('accessory_id', $ids)->delete();
		DB::table('accessories')->whereIn('id', $ids)->delete();
	}
};
