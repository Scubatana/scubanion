<?php

namespace App\Models;

use App\Enums\Ambition;
use App\Enums\QuestRepeat;
use App\Enums\QuestStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['name', 'description', 'icon', 'auto_assign', 'target_ambition', 'target_zone_id', 'repeat', 'starts_at', 'ends_at', 'status'])]
class Quest extends Model
{
	use HasFactory;

	public const EDITABLE_ONCE_STARTED = ['name', 'description', 'icon', 'status', 'updated_at'];

	protected static function booted(): void
	{
		static::updating(function (Quest $quest): void {
			if ($quest->isStarted() && array_diff(array_keys($quest->getDirty()), self::EDITABLE_ONCE_STARTED) !== []) {
				throw new LogicException('A started quest can only change its texts and its status.');
			}
		});
	}

	protected function casts(): array
	{
		return [
			'auto_assign' => 'boolean',
			'target_ambition' => Ambition::class,
			'repeat' => QuestRepeat::class,
			'status' => QuestStatus::class,
			'starts_at' => 'datetime',
			'ends_at' => 'datetime',
		];
	}

	public function tasks(): HasMany
	{
		return $this->hasMany(QuestTask::class)->orderBy('step')->orderBy('position');
	}

	public function rewards(): HasMany
	{
		return $this->hasMany(QuestReward::class);
	}

	public function targetZone(): BelongsTo
	{
		return $this->belongsTo(Place::class, 'target_zone_id');
	}

	public function userQuests(): HasMany
	{
		return $this->hasMany(UserQuest::class);
	}

	#[Scope]
	protected function availableAt(Builder $query, CarbonInterface $moment): void
	{
		$query
			->where('status', QuestStatus::Published)
			->where(fn (Builder $starts) => $starts->whereNull('starts_at')->orWhere('starts_at', '<=', $moment))
			->where(fn (Builder $ends) => $ends->whereNull('ends_at')->orWhere('ends_at', '>', $moment));
	}

	public function isStarted(): bool
	{
		return $this->exists && $this->userQuests()->exists();
	}

	public function stepCount(): int
	{
		return (int) $this->tasks()->max('step');
	}
}
