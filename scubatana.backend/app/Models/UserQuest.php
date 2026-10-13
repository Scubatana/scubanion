<?php

namespace App\Models;

use App\Enums\QuestSource;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'quest_id', 'period', 'current_step', 'source', 'assigned_at', 'expires_at', 'completed_at', 'failed_at'])]
class UserQuest extends Model
{
	use HasFactory;

	protected function casts(): array
	{
		return [
			'current_step' => 'integer',
			'source' => QuestSource::class,
			'assigned_at' => 'datetime',
			'expires_at' => 'datetime',
			'completed_at' => 'datetime',
			'failed_at' => 'datetime',
		];
	}

	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}

	public function quest(): BelongsTo
	{
		return $this->belongsTo(Quest::class);
	}

	public function tasks(): HasMany
	{
		return $this->hasMany(UserQuestTask::class);
	}

	public function rewards(): HasMany
	{
		return $this->hasMany(UserReward::class);
	}

	#[Scope]
	protected function activeAt(Builder $query, CarbonInterface $moment): void
	{
		$query
			->whereNull('completed_at')
			->whereNull('failed_at')
			->where(fn (Builder $expires) => $expires->whereNull('expires_at')->orWhere('expires_at', '>', $moment));
	}

	public function isActiveAt(CarbonInterface $moment): bool
	{
		return $this->completed_at === null
			&& $this->failed_at === null
			&& ($this->expires_at === null || $this->expires_at->gt($moment));
	}
}
