<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\CompanionService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
	public function run(): void
	{
		$user = User::factory()->create([
			'email' => 'test@example.com',
			'pseudo' => 'MarcheurDuSoir',
		]);

		app(CompanionService::class)->createFor($user);

		$this->call(QuestSeeder::class);
	}
}
