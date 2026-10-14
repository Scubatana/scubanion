<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'accessory_id', 'quest_id', 'unlocked_at'])]
#[WithoutTimestamps]
class UserAccessory extends Model
{
	protected function casts(): array
	{
		return [
			'unlocked_at' => 'datetime',
		];
	}

	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}

	public function accessory(): BelongsTo
	{
		return $this->belongsTo(Accessory::class);
	}

	public function quest(): BelongsTo
	{
		return $this->belongsTo(Quest::class);
	}
}
