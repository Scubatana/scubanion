<?php

namespace App\Models;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['user_id', 'reported_id', 'reason', 'comment', 'status'])]
class Report extends Model
{
	protected $attributes = [
		'status' => 'pending',
	];

	protected static function booted(): void
	{
		static::saving(function (Report $report): void {
			if ((int) $report->user_id === (int) $report->reported_id) {
				throw new InvalidArgumentException('A player cannot report themselves.');
			}
		});
	}

	protected function casts(): array
	{
		return [
			'user_id' => 'integer',
			'reported_id' => 'integer',
			'reason' => ReportReason::class,
			'status' => ReportStatus::class,
		];
	}

	public function reporter(): BelongsTo
	{
		return $this->belongsTo(User::class, 'user_id');
	}

	public function reported(): BelongsTo
	{
		return $this->belongsTo(User::class, 'reported_id');
	}

	#[Scope]
	protected function pending(Builder $query): void
	{
		$query->where('status', ReportStatus::Pending);
	}
}
