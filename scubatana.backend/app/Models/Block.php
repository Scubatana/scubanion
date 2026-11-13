<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['user_id', 'blocked_id'])]
class Block extends Model
{
	protected static function booted(): void
	{
		static::saving(function (Block $block): void {
			if ((int) $block->user_id === (int) $block->blocked_id) {
				throw new InvalidArgumentException('A player cannot block themselves.');
			}
		});
	}

	protected function casts(): array
	{
		return [
			'user_id' => 'integer',
			'blocked_id' => 'integer',
		];
	}

	public function blocker(): BelongsTo
	{
		return $this->belongsTo(User::class, 'user_id');
	}

	public function blocked(): BelongsTo
	{
		return $this->belongsTo(User::class, 'blocked_id');
	}

	#[Scope]
	protected function between(Builder $query, User $player, User $other): void
	{
		$query->where(fn (Builder $pair) => $pair
			->where(fn (Builder $mine) => $mine->where('user_id', $player->id)->where('blocked_id', $other->id))
			->orWhere(fn (Builder $theirs) => $theirs->where('user_id', $other->id)->where('blocked_id', $player->id)));
	}
}
