<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'user_quest_id', 'quest_reward_id', 'xp', 'accessory_id', 'earned_at', 'claimed_at'])]
class UserReward extends Model
{
	protected function casts(): array
	{
		return [
			'xp' => 'integer',
			'earned_at' => 'datetime',
			'claimed_at' => 'datetime',
		];
	}

	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}

	public function userQuest(): BelongsTo
	{
		return $this->belongsTo(UserQuest::class);
	}

	public function questReward(): BelongsTo
	{
		return $this->belongsTo(QuestReward::class);
	}

	public function accessory(): BelongsTo
	{
		return $this->belongsTo(Accessory::class);
	}

	#[Scope]
	protected function unclaimed(Builder $query): void
	{
		$query->whereNull('claimed_at');
	}
}
