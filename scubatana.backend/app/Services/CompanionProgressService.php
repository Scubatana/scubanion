<?php

namespace App\Services;

use App\Models\Companion;
use App\Models\Passage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CompanionProgressService
{
	public const STEPS_PER_XP = 10;

	public const GOAL_BONUS_XP = 50;

	public const HEALTH_DAYS = 7;

	public function __construct(
		private StepService $steps,
		private CompanionService $companions,
	) {}

	public function refresh(User $user): ?Companion
	{
		$companion = $user->companion;

		if ($companion === null) {
			return null;
		}

		$today = CarbonImmutable::now()->startOfDay();
		$joined = CarbonImmutable::instance($user->created_at)->startOfDay();
		$days = $this->days($user, $today);
		[$level, $xp] = $this->levelFor($this->totalXp($days) + $this->rewardXp($user));

		$companion->update([
			'level' => $level,
			'xp' => $xp,
			'mood' => $this->mood($days, $today, $joined),
			'health' => $this->health($days, $today, $joined),
		]);

		return $companion;
	}

	public function totalXp(Collection $days): int
	{
		return (int) $days->sum(fn (array $day) => intdiv($day['steps'], self::STEPS_PER_XP) + ($day['steps'] >= $day['goal'] ? self::GOAL_BONUS_XP : 0));
	}

	public function rewardXp(User $user): int
	{
		return (int) $user->rewards()->whereNotNull('claimed_at')->sum('xp')
			+ (int) Passage::where('user_id', $user->id)->sum('reward_xp');
	}

	public function levelFor(int $totalXp): array
	{
		$level = 1;

		while ($totalXp >= $this->companions->xpForNextLevel($level)) {
			$totalXp -= $this->companions->xpForNextLevel($level);
			$level++;
		}

		return [$level, $totalXp];
	}

	private function days(User $user, CarbonImmutable $today): Collection
	{
		$firstDay = $user->activities()->min('day');

		return $firstDay === null ? collect() : $this->steps->dailyTotals($user, CarbonImmutable::parse($firstDay)->startOfDay(), $today);
	}

	private function mood(Collection $days, CarbonImmutable $today, CarbonImmutable $joined): int
	{
		return (int) round(100 * max($this->ratio($days, $today, $joined), $this->ratio($days, $today->subDay(), $joined)));
	}

	private function health(Collection $days, CarbonImmutable $today, CarbonImmutable $joined): int
	{
		$ratios = array_map(fn (int $back) => $this->ratio($days, $today->subDays($back), $joined), range(1, self::HEALTH_DAYS));

		return (int) round(100 * array_sum($ratios) / self::HEALTH_DAYS);
	}

	private function ratio(Collection $days, CarbonImmutable $day, CarbonImmutable $joined): float
	{
		if ($day->lt($joined)) {
			return 1.0;
		}

		$total = $days[$day->toDateString()] ?? null;

		return $total === null ? 0.0 : min(1.0, $total['steps'] / $total['goal']);
	}
}
