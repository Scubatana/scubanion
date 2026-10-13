<?php

namespace App\Models;

use App\Enums\Ambition;
use App\Enums\Gender;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['email', 'pseudo', 'birth_date', 'gender', 'ambition', 'daily_step_goal', 'terms_accepted_at'])]
class User extends Authenticatable
{
	use HasApiTokens, HasFactory;

	protected function casts(): array
	{
		return [
			'birth_date' => 'date',
			'gender' => Gender::class,
			'ambition' => Ambition::class,
			'daily_step_goal' => 'integer',
			'terms_accepted_at' => 'datetime',
		];
	}

	public function companion(): HasOne
	{
		return $this->hasOne(Companion::class);
	}

	public function activities(): HasMany
	{
		return $this->hasMany(Activity::class);
	}

	public function hasCompletedProfile(): bool
	{
		return $this->pseudo !== null;
	}
}
