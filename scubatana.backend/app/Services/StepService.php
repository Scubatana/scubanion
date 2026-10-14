<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StepService
{
	public function record(User $user, string $deviceId, string $day, int $steps): Activity
	{
		return DB::transaction(function () use ($user, $deviceId, $day, $steps) {
			User::whereKey($user->id)->lockForUpdate()->first();

			$sameDay = $user->activities()->whereDate('day', $day)->get();
			$activity = $sameDay->firstWhere('device_id', $deviceId)
				?? new Activity(['user_id' => $user->id, 'day' => $day, 'device_id' => $deviceId, 'steps' => 0, 'source' => 'phone']);

			if ($activity->goal === null || $day === CarbonImmutable::now()->toDateString()) {
				$activity->goal = $user->daily_step_goal;
			}

			if ($steps > $activity->steps) {
				$otherPhones = (int) $sameDay->where('device_id', '!==', $deviceId)->sum('steps');
				$ceiling = max($activity->steps, config('steps.max_per_day') - $otherPhones);

				$activity->steps = min($steps, $activity->steps + $this->allowedGain($sameDay, $day), $ceiling);
			}

			$activity->save();

			return $activity;
		});
	}

	private function allowedGain(Collection $sameDay, string $day): int
	{
		$dayStart = CarbonImmutable::parse($day)->startOfDay();
		$lastUpdate = $sameDay->max('updated_at');
		$from = $lastUpdate === null ? $dayStart : CarbonImmutable::instance($lastUpdate)->max($dayStart);
		$until = CarbonImmutable::now()->min($dayStart->endOfDay());
		$seconds = max(0, (int) $from->diffInSeconds($until, false));

		return (int) ceil($seconds * config('steps.max_per_hour') / 3600);
	}

	public function changeGoal(User $user, int $goal): void
	{
		$user->update(['daily_step_goal' => $goal]);
		$user->activities()->whereDate('day', CarbonImmutable::now()->toDateString())->update(['goal' => $goal]);
	}

	public function dailyTotals(User $user, CarbonImmutable $from, CarbonImmutable $to): Collection
	{
		return $user->activities()
			->whereDate('day', '>=', $from->toDateString())
			->whereDate('day', '<=', $to->toDateString())
			->get()
			->groupBy(fn (Activity $activity) => $activity->day->toDateString())
			->map(fn (Collection $activities) => [
				'steps' => min((int) $activities->sum('steps'), config('steps.max_per_day')),
				'goal' => (int) ($activities->max('goal') ?? $user->daily_step_goal),
			]);
	}

	public function totalSteps(User $user): int
	{
		$firstDay = $user->activities()->min('day');

		if ($firstDay === null) {
			return 0;
		}

		return (int) $this->dailyTotals($user, CarbonImmutable::parse($firstDay)->startOfDay(), CarbonImmutable::now()->startOfDay())->sum('steps');
	}

	public function today(User $user): array
	{
		$today = CarbonImmutable::now()->startOfDay();

		return [
			'date' => $today->toDateString(),
			'steps' => $this->dailyTotals($user, $today, $today)[$today->toDateString()]['steps'] ?? 0,
			'goal' => $user->daily_step_goal,
		];
	}

	public function streak(User $user): array
	{
		$today = CarbonImmutable::now()->startOfDay();
		$reached = $this->dailyTotals($user, $today->subDays(400), $today)->filter(fn (array $day) => $day['steps'] >= $day['goal']);

		$active = $reached->has($today->toDateString());
		$days = 0;

		for ($day = $active ? $today : $today->subDay(); $reached->has($day->toDateString()); $day = $day->subDay()) {
			$days++;
		}

		return ['days' => $days, 'active' => $active];
	}

	public function firstMonth(User $user): CarbonImmutable
	{
		return CarbonImmutable::instance($user->created_at)->startOfMonth();
	}

	public function month(User $user, CarbonImmutable $month): array
	{
		$start = $month->startOfMonth();
		$today = CarbonImmutable::now()->startOfDay();
		$lastCountedDay = $start->endOfMonth()->startOfDay()->min($today);
		$totals = $this->dailyTotals($user, $start, $start->endOfMonth());

		$days = [];

		for ($day = $start; $day->lte($lastCountedDay); $day = $day->addDay()) {
			$total = $totals[$day->toDateString()] ?? ['steps' => 0, 'goal' => $user->daily_step_goal];
			$goal = $day->eq($today) ? $user->daily_step_goal : $total['goal'];
			$days[] = ['day' => $day->toDateString(), 'steps' => $total['steps'], 'goal' => $goal, 'goal_reached' => $total['steps'] >= $goal];
		}

		$total = array_sum(array_column($days, 'steps'));
		$best = collect($days)->where('steps', '>', 0)->sortByDesc('steps')->first();

		return [
			'month' => $start->format('Y-m'),
			'goal' => $user->daily_step_goal,
			'has_previous' => $start->gt($this->firstMonth($user)),
			'has_next' => $start->lt($today->startOfMonth()),
			'streak' => $this->streak($user),
			'days' => $days,
			'stats' => [
				'total' => $total,
				'average' => $days === [] ? 0 : (int) round($total / count($days)),
				'active_days' => count(array_filter($days, fn (array $day) => $day['steps'] > 0)),
				'goal_days' => count(array_filter($days, fn (array $day) => $day['goal_reached'])),
				'best' => $best ? ['day' => $best['day'], 'steps' => $best['steps']] : null,
				'longest_streak' => $this->longestStreak($days),
			],
		];
	}

	private function longestStreak(array $days): int
	{
		$longest = 0;
		$current = 0;

		foreach ($days as $day) {
			$current = $day['goal_reached'] ? $current + 1 : 0;
			$longest = max($longest, $current);
		}

		return $longest;
	}
}
