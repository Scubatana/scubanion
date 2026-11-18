<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[Fillable(['name', 'description', 'starts_at', 'ends_at', 'bonus_xp', 'accessory_id', 'all_terminals', 'active'])]
class Event extends Model
{
	use HasFactory;

	protected static function booted(): void
	{
		static::saving(function (Event $event): void {
			if ($event->starts_at === null || $event->ends_at === null || $event->ends_at->lte($event->starts_at)) {
				throw new InvalidArgumentException('An event must end after it starts.');
			}

			if ($event->accessory_id !== null && ! $event->accessory->earnable) {
				throw new InvalidArgumentException('Only an earnable accessory can be given by an event.');
			}

			if ($event->bonus_xp <= 0 && $event->accessory_id === null) {
				throw new InvalidArgumentException('An event must give experience, an accessory or both.');
			}
		});
	}

	protected function casts(): array
	{
		return [
			'starts_at' => 'datetime',
			'ends_at' => 'datetime',
			'bonus_xp' => 'integer',
			'all_terminals' => 'boolean',
			'active' => 'boolean',
		];
	}

	public function accessory(): BelongsTo
	{
		return $this->belongsTo(Accessory::class);
	}

	public function terminals(): BelongsToMany
	{
		return $this->belongsToMany(Terminal::class);
	}

	public function eventPassages(): HasMany
	{
		return $this->hasMany(EventPassage::class);
	}

	#[Scope]
	protected function inProgressAt(Builder $query, CarbonInterface $moment): void
	{
		$query->where('active', true)->where('starts_at', '<=', $moment)->where('ends_at', '>', $moment);
	}

	#[Scope]
	protected function notFinishedAt(Builder $query, CarbonInterface $moment): void
	{
		$query->where('active', true)->where('ends_at', '>', $moment);
	}

	#[Scope]
	protected function atTerminal(Builder $query, Terminal $terminal): void
	{
		$query->where(fn (Builder $where) => $where
			->where('all_terminals', true)
			->orWhereHas('terminals', fn (Builder $terminals) => $terminals->whereKey($terminal->id)));
	}

	public function isInProgressAt(CarbonInterface $moment): bool
	{
		return $this->active && $this->starts_at->lte($moment) && $this->ends_at->gt($moment);
	}
}
