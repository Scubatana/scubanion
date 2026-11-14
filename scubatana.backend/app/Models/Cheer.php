<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Coup de patte : user_id l'envoie à friend_id, au plus une fois par jour (heure de Paris).
#[Fillable(['user_id', 'friend_id', 'day'])]
class Cheer extends Model
{
	protected function casts(): array
	{
		return [
			'user_id' => 'integer',
			'friend_id' => 'integer',
			'day' => 'date',
		];
	}

	public function sender(): BelongsTo
	{
		return $this->belongsTo(User::class, 'user_id');
	}

	public function receiver(): BelongsTo
	{
		return $this->belongsTo(User::class, 'friend_id');
	}
}
