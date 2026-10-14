<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

#[Fillable(['email', 'code_hash', 'attempts', 'expires_at', 'consumed_at'])]
class LoginCode extends Model
{
	use Prunable;

	protected function casts(): array
	{
		return [
			'attempts' => 'integer',
			'expires_at' => 'datetime',
			'consumed_at' => 'datetime',
		];
	}

	public function prunable(): Builder
	{
		return static::where('expires_at', '<', now()->subDay());
	}
}
