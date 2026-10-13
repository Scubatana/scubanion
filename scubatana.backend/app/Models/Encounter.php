<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['user_id', 'other_user_id', 'met_at', 'zone', 'validated_at'])]
class Encounter extends Model
{
	use HasFactory;

	protected static function booted(): void
	{
		static::saving(function (Encounter $encounter): void {
			$first = (int) $encounter->user_id;
			$second = (int) $encounter->other_user_id;

			if ($first === $second) {
				throw new InvalidArgumentException('A player cannot meet themselves.');
			}

			$encounter->user_id = min($first, $second);
			$encounter->other_user_id = max($first, $second);
		});
	}

	protected function casts(): array
	{
		return [
			'user_id' => 'integer',
			'other_user_id' => 'integer',
			'met_at' => 'datetime',
			'validated_at' => 'datetime',
		];
	}

	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}

	public function otherUser(): BelongsTo
	{
		return $this->belongsTo(User::class, 'other_user_id');
	}

	#[Scope]
	protected function involving(Builder $query, User $player): void
	{
		$query->where(fn (Builder $either) => $either->where('user_id', $player->id)->orWhere('other_user_id', $player->id));
	}

	public function isValidated(): bool
	{
		return $this->validated_at !== null;
	}
}
