<?php

namespace Tests\Unit\Models;

use App\Enums\Ambition;
use App\Enums\QuestType;
use App\Models\Place;
use App\Models\Quest;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestTest extends TestCase
{
	use RefreshDatabase;

	public function test_it_reads_back_the_type_the_ambition_and_the_reward(): void
	{
		$quest = Quest::factory()->create([
			'type' => QuestType::Quiz,
			'ambition' => Ambition::Explore,
			'goal' => 3,
			'reward_xp' => 250,
		])->fresh();

		$this->assertSame(QuestType::Quiz, $quest->type);
		$this->assertSame(Ambition::Explore, $quest->ambition);
		$this->assertSame(3, $quest->goal);
		$this->assertSame(250, $quest->reward_xp);
	}

	public function test_a_place_quest_points_to_its_place(): void
	{
		$quest = Quest::factory()->atPlace()->create();

		$this->assertSame(QuestType::Place, $quest->type);
		$this->assertTrue($quest->place->is(Place::sole()));
		$this->assertTrue(Place::sole()->quests->first()->is($quest));
	}

	public function test_a_place_used_by_a_quest_cannot_be_deleted(): void
	{
		$quest = Quest::factory()->atPlace()->create();

		$this->expectException(QueryException::class);

		$quest->place->delete();
	}

	public function test_available_at_keeps_the_permanent_quests_and_the_running_challenges(): void
	{
		$this->travelTo('2026-10-14 12:00:00');
		$permanent = Quest::factory()->create();
		$thisWeek = Quest::factory()->create(['starts_at' => '2026-10-12 00:00:00', 'ends_at' => '2026-10-19 00:00:00']);
		Quest::factory()->create(['starts_at' => '2026-10-19 00:00:00', 'ends_at' => '2026-10-26 00:00:00']);
		Quest::factory()->create(['starts_at' => '2026-10-05 00:00:00', 'ends_at' => '2026-10-12 00:00:00']);

		$available = Quest::availableAt(now())->orderBy('id')->pluck('id')->all();

		$this->assertSame([$permanent->id, $thisWeek->id], $available);
	}
}
