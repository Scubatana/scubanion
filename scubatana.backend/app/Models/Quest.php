<?php

namespace App\Models;

use App\Enums\Ambition;
use App\Enums\QuestType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'description', 'type', 'ambition', 'goal', 'place_id', 'reward_xp', 'starts_at', 'ends_at'])]
class Quest extends Model
{
	use HasFactory;

	protected function casts(): array
	{
		return [
			'type' => QuestType::class,
			'ambition' => Ambition::class,
			'goal' => 'integer',
			'reward_xp' => 'integer',
			'starts_at' => 'datetime',
			'ends_at' => 'datetime',
		];
	}

	public function place(): BelongsTo
	{
		return $this->belongsTo(Place::class);
	}

	public function progress(): HasMany
	{
		return $this->hasMany(QuestProgress::class);
	}

	#[Scope]
	protected function availableAt(Builder $query, CarbonInterface $moment): void
	{
		$query
			->where(fn (Builder $starts) => $starts->whereNull('starts_at')->orWhere('starts_at', '<=', $moment))
			->where(fn (Builder $ends) => $ends->whereNull('ends_at')->orWhere('ends_at', '>', $moment));
	}
}
