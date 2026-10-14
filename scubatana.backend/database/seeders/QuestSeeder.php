<?php

namespace Database\Seeders;

use App\Enums\Ambition;
use App\Enums\QuestRepeat;
use App\Enums\QuestStatus;
use App\Models\Accessory;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\Quest;
use App\Models\TaskType;
use Illuminate\Database\Seeder;

class QuestSeeder extends Seeder
{
	public function run(): void
	{
		$types = PlaceType::pluck('id', 'code');
		$tasks = TaskType::pluck('id', 'code');
		$accessories = Accessory::pluck('id', 'code');

		$place = fn (string $name, string $type, float $latitude, float $longitude, ?int $radius = null) => Place::firstOrCreate(
			['name' => $name],
			['place_type_id' => $types[$type], 'latitude' => $latitude, 'longitude' => $longitude, 'radius_m' => $radius],
		);

		$castle = $place('Colline du Château', 'point_of_interest', 43.6953, 7.2806, 150);
		$place('Jardin Albert Ier', 'park', 43.6956, 7.2686);
		$place('Promenade du Paillon', 'park', 43.6998, 7.2770, 250);
		$place('Parc Phoenix', 'park', 43.6669, 7.2153, 200);
		$cafe = $place('Café partenaire', 'partner', 43.6976, 7.2706, 50);

		$centre = $place('Centre de Nice', 'zone', 43.6990, 7.2720);

		if ($centre->points()->doesntExist()) {
			$centre->setOutline([[43.7080, 7.2580], [43.7080, 7.2880], [43.6900, 7.2880], [43.6900, 7.2580]]);
		}

		$quests = [
			[
				'quest' => ['name' => 'Premiers pas', 'description' => 'Tes premières balades avec ton compagnon.', 'icon' => 'shoe', 'auto_assign' => true, 'target_ambition' => Ambition::Fitness],
				'tasks' => [
					[1, 'steps', 'Marche 3 000 pas', 3000],
					[2, 'steps', 'Marche encore 7 000 pas', 7000],
					[2, 'visit_place_type', 'Passe par un parc', 1, ['place_type_id' => $types['park']]],
				],
				'rewards' => [['step' => 1, 'xp' => 50], ['xp' => 150]],
			],
			[
				'quest' => ['name' => 'Le belvédère', 'description' => 'Monte jusqu\'à la Colline du Château.', 'icon' => 'landmark', 'auto_assign' => true, 'target_ambition' => Ambition::Explore],
				'tasks' => [
					[1, 'visit_place', 'Rejoins la Colline du Château', 1, ['place_id' => $castle->id]],
					[2, 'quiz', 'Réponds au quiz du belvédère', 2, ['params' => ['questions' => [
						['question' => 'Quelle mer vois-tu depuis la colline ?', 'choices' => ['La Manche', 'La Méditerranée', 'La mer du Nord'], 'answer' => 1],
						['question' => 'Qu\'y avait-il autrefois en haut de la colline ?', 'choices' => ['Un château', 'Un phare', 'Un moulin'], 'answer' => 0],
					]]]],
				],
				'rewards' => [['xp' => 200, 'accessory' => 'crown']],
			],
			[
				'quest' => ['name' => 'Balade du jour', 'description' => 'Une balade par jour.', 'icon' => 'sun', 'auto_assign' => true, 'repeat' => QuestRepeat::Daily],
				'tasks' => [[1, 'steps', 'Marche 5 000 pas aujourd\'hui', 5000]],
				'rewards' => [['xp' => 50]],
			],
			[
				'quest' => ['name' => 'Défi de la semaine', 'description' => 'Tiens le rythme toute la semaine.', 'icon' => 'flame', 'auto_assign' => true, 'repeat' => QuestRepeat::Weekly],
				'tasks' => [
					[1, 'steps', 'Marche 35 000 pas cette semaine', 35000],
					[1, 'meet_player', 'Croise un autre joueur', 1],
				],
				'rewards' => [['xp' => 300, 'accessory' => 'medal']],
			],
			[
				'quest' => ['name' => 'Pause café', 'description' => 'Une quête offerte par un partenaire du centre-ville.', 'icon' => 'store', 'auto_assign' => true, 'target_zone_id' => $centre->id],
				'tasks' => [
					[1, 'visit_place', 'Passe au café partenaire', 1, ['place_id' => $cafe->id]],
					[2, 'scan_code', 'Scanne le code affiché au comptoir', 1, ['params' => ['code' => 'SCUBA-CAFE']]],
				],
				'rewards' => [['task' => 0, 'accessory' => 'diving_mask'], ['xp' => 100]],
			],
		];

		foreach ($quests as $definition) {
			$quest = Quest::firstOrCreate(['name' => $definition['quest']['name']], [...$definition['quest'], 'status' => QuestStatus::Published]);

			if ($quest->tasks()->exists()) {
				continue;
			}

			$positions = [];
			$created = [];

			foreach ($definition['tasks'] as $task) {
				[$step, $type, $description, $target, $extra] = array_pad($task, 5, []);
				$positions[$step] = ($positions[$step] ?? 0) + 1;
				$created[] = $quest->tasks()->create([
					'step' => $step,
					'position' => $positions[$step],
					'task_type_id' => $tasks[$type],
					'description' => $description,
					'target' => $target,
					...$extra,
				]);
			}

			foreach ($definition['rewards'] as $reward) {
				$quest->rewards()->create([
					'step' => $reward['step'] ?? null,
					'quest_task_id' => isset($reward['task']) ? $created[$reward['task']]->id : null,
					'xp' => $reward['xp'] ?? 0,
					'accessory_id' => isset($reward['accessory']) ? $accessories[$reward['accessory']] : null,
				]);
			}
		}
	}
}
