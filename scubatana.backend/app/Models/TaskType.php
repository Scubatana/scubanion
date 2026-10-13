<?php

namespace App\Models;

use App\Enums\AppAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'label', 'app_action', 'params_schema', 'active'])]
class TaskType extends Model
{
	use HasFactory;

	protected function casts(): array
	{
		return [
			'app_action' => AppAction::class,
			'params_schema' => 'array',
			'active' => 'boolean',
		];
	}

	public static function byCode(string $code): self
	{
		return static::where('code', $code)->firstOrFail();
	}
}
