<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'label', 'category', 'image', 'earnable', 'active'])]
class Accessory extends Model
{
	protected function casts(): array
	{
		return [
			'earnable' => 'boolean',
			'active' => 'boolean',
		];
	}

	#[Scope]
	protected function active(Builder $query): void
	{
		$query->where('active', true);
	}

	public function dataUri(): string
	{
		return 'data:image/svg+xml;base64,'.base64_encode($this->image);
	}
}
