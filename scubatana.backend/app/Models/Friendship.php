<?php

namespace App\Models;

use App\Enums\FriendshipStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'friend_id', 'status', 'accepted_at'])]
class Friendship extends Model
{
	protected function casts(): array
	{
		return [
			'user_id' => 'integer',
			'friend_id' => 'integer',
			'status' => FriendshipStatus::class,
			'accepted_at' => 'datetime',
		];
	}

	public function requester(): BelongsTo
	{
		return $this->belongsTo(User::class, 'user_id');
	}

	public function invitee(): BelongsTo
	{
		return $this->belongsTo(User::class, 'friend_id');
	}

	#[Scope]
	protected function involving(Builder $query, User $player): void
	{
		$query->where(fn (Builder $either) => $either->where('user_id', $player->id)->orWhere('friend_id', $player->id));
	}

	#[Scope]
	protected function between(Builder $query, User $player, User $other): void
	{
		$query->where(fn (Builder $pair) => $pair
			->where(fn (Builder $ours) => $ours->where('user_id', $player->id)->where('friend_id', $other->id))
			->orWhere(fn (Builder $theirs) => $theirs->where('user_id', $other->id)->where('friend_id', $player->id)));
	}

	#[Scope]
	protected function accepted(Builder $query): void
	{
		$query->where('status', FriendshipStatus::Accepted);
	}

	public function otherThan(User $player): User
	{
		return $this->user_id === $player->id ? $this->invitee : $this->requester;
	}
}
