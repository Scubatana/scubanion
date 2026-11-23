<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('event_passage')]
#[Fillable(['passage_id', 'event_id', 'bonus_xp', 'accessory_id'])]
#[WithoutTimestamps]
class EventPassage extends Model
{
	protected function casts(): array
	{
		return [
			'bonus_xp' => 'integer',
		];
	}

	public function passage(): BelongsTo
	{
		return $this->belongsTo(Passage::class);
	}

	public function event(): BelongsTo
	{
		return $this->belongsTo(Event::class);
	}

	public function accessory(): BelongsTo
	{
		return $this->belongsTo(Accessory::class);
	}
}
