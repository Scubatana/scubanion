<?php

namespace Tests\Feature\Quests;

use App\Enums\QuestSource;
use App\Models\Companion;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\Quest;
use App\Models\QuestTask;
use App\Models\TaskType;
use App\Models\User;
use App\Models\UserQuest;
use App\Models\UserReward;
use App\Services\QuestEngine;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('mariadb')]
class QuestConcurrencyTest extends TestCase
{
	use DatabaseTruncation;

	private const ROUNDS = 5;

	protected $exceptTables = ['migrations', 'place_types', 'task_types', 'accessories'];

	private User $lea;

	protected function setUp(): void
	{
		if (getenv('RUN_CONCURRENCY_TESTS') !== '1' || ! in_array(getenv('DB_CONNECTION'), ['mysql', 'mariadb'], true) || ! function_exists('pcntl_fork')) {
			$this->markTestSkipped('Concurrency tests need RUN_CONCURRENCY_TESTS=1, a MariaDB database and the pcntl extension.');
		}

		parent::setUp();

		$this->lea = User::factory()->create(['pseudo' => 'Lea']);
		Companion::factory()->for($this->lea)->create();
		Sanctum::actingAs($this->lea);
	}

	private function simultaneously(callable ...$requests): array
	{
		$start = microtime(true) + 0.5;
		$files = [];
		$children = [];
		DB::disconnect();

		foreach ($requests as $index => $request) {
			$files[$index] = tempnam(sys_get_temp_dir(), 'scubanion-race-');
			$pid = pcntl_fork();

			if ($pid === 0) {
				DB::purge();
				time_sleep_until($start);

				try {
					$result = $request();
				} catch (\Throwable $error) {
					$result = 'exception: '.$error::class;
				}

				file_put_contents($files[$index], serialize($result));
				posix_kill(posix_getpid(), SIGKILL);
			}

			$children[] = $pid;
		}

		foreach ($children as $pid) {
			pcntl_waitpid($pid, $status);
		}

		DB::reconnect();

		return array_map(function (string $file): mixed {
			$result = unserialize((string) file_get_contents($file));
			unlink($file);

			return $result;
		}, $files);
	}

	private function quest(string $name, array $tasks, array $rewards = []): UserQuest
	{
		$quest = Quest::factory()->create(['name' => $name]);

		foreach ($tasks as $index => [$step, $type, $target, $extra]) {
			QuestTask::factory()->for($quest)->create(['step' => $step, 'position' => $index + 1, 'task_type_id' => TaskType::byCode($type)->id, 'target' => $target, ...$extra]);
		}

		foreach ($rewards as $reward) {
			$quest->rewards()->create($reward);
		}

		return app(QuestEngine::class)->give($this->lea, $quest->fresh(), QuestSource::Manual, now());
	}

	private function parks(): array
	{
		return [
			Place::factory()->create(['name' => 'Jardin Albert Ier', 'latitude' => 43.6956, 'longitude' => 7.2686]),
			Place::factory()->create(['name' => 'Parc du Château', 'latitude' => 43.6953, 'longitude' => 7.2806]),
		];
	}

	private function checkIn(UserQuest $userQuest, Place $place): callable
	{
		$taskId = $userQuest->tasks()->value('quest_task_id');

		return fn () => $this->postJson("/api/me/quests/{$userQuest->id}/tasks/{$taskId}", ['latitude' => $place->latitude, 'longitude' => $place->longitude])->status();
	}

	public function test_two_check_ins_at_two_places_sent_together_both_count(): void
	{
		[$albert, $castle] = $this->parks();
		$parkType = PlaceType::where('code', 'park')->value('id');

		foreach (range(1, self::ROUNDS) as $round) {
			$userQuest = $this->quest("Deux parcs {$round}", [[1, 'visit_place_type', 2, ['place_type_id' => $parkType]]], [['xp' => 50]]);

			$statuses = $this->simultaneously($this->checkIn($userQuest, $albert), $this->checkIn($userQuest, $castle));

			$this->assertSame([200, 200], $statuses, "Round {$round}");
			$this->assertSame(2, $userQuest->tasks()->value('value'), "Round {$round}");
			$this->assertNotNull($userQuest->fresh()->completed_at, "Round {$round}");
			$this->assertSame(1, UserReward::where('user_quest_id', $userQuest->id)->count(), "Round {$round}");
		}
	}

	public function test_the_same_check_in_sent_twice_together_counts_once_without_error(): void
	{
		[$albert] = $this->parks();
		$parkType = PlaceType::where('code', 'park')->value('id');

		foreach (range(1, self::ROUNDS) as $round) {
			$userQuest = $this->quest("Un parc deux fois {$round}", [[1, 'visit_place_type', 2, ['place_type_id' => $parkType]]]);

			$statuses = $this->simultaneously($this->checkIn($userQuest, $albert), $this->checkIn($userQuest, $albert));
			sort($statuses);

			$this->assertSame([200, 422], $statuses, "Round {$round}");
			$this->assertSame(1, $userQuest->tasks()->value('value'), "Round {$round}");
		}
	}

	public function test_two_step_syncs_sent_together_finish_a_step_and_give_its_reward_once(): void
	{
		foreach (range(1, self::ROUNDS) as $round) {
			$userQuest = $this->quest("Marche {$round}", [[1, 'steps', 10, []], [2, 'steps', 1000000, []]], [['step' => 1, 'xp' => 70]]);
			$this->lea->activities()->create(['day' => now()->toDateString(), 'device_id' => "phone-{$round}", 'steps' => 500, 'goal' => 5000]);
			$sync = fn () => app(QuestEngine::class)->refreshPlayer(User::find($this->lea->id)) ?? 'ok';

			$results = $this->simultaneously($sync, $sync);

			$this->assertSame(['ok', 'ok'], $results, "Round {$round}");
			$this->assertSame(2, $userQuest->fresh()->current_step, "Round {$round}");
			$this->assertSame(2, $userQuest->tasks()->count(), "Round {$round}");
			$this->assertSame(1, UserReward::where('user_quest_id', $userQuest->id)->count(), "Round {$round}");
		}
	}
}
