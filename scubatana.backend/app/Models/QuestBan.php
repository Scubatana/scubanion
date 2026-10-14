<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'reason', 'evidence', 'starts_at', 'ends_at'])]
class QuestBan extends Model
{
	use HasFactory;

	protected function casts(): array
	{
		return [
			'evidence' => 'array',
			'starts_at' => 'datetime',
			'ends_at' => 'datetime',
		];
	}

	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}

	#[Scope]
	protected function activeAt(Builder $query, CarbonInterface $moment): void
	{
		$query->where('starts_at', '<=', $moment)->where('ends_at', '>', $moment);
	}
}
