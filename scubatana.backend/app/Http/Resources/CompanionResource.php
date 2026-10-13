<?php

namespace App\Http\Resources;

use App\Services\CompanionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanionResource extends JsonResource
{
	public function toArray(Request $request): array
	{
		return [
			'name' => $this->name,
			'species' => $this->species,
			'color' => $this->color ?? CompanionService::DEFAULT_COLOR,
			'accessories' => $this->accessories ?? [],
			'unlocked_accessories' => $this->user->accessories()->orderBy('unlocked_at')->pluck('accessory')->all(),
			'level' => $this->level,
			'xp' => $this->xp,
			'xp_for_next_level' => app(CompanionService::class)->xpForNextLevel($this->level),
			'mood' => $this->mood,
			'health' => $this->health,
		];
	}
}
