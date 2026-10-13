<?php

namespace App\Http\Resources;

use App\Services\FriendshipService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerResource extends JsonResource
{
	public function toArray(Request $request): array
	{
		return [
			'id' => $this->id,
			'email' => $this->email,
			'pseudo' => $this->pseudo,
			'birth_date' => $this->birth_date?->toDateString(),
			'gender' => $this->gender?->value,
			'ambition' => $this->ambition?->value,
			'profile_completed' => $this->hasCompletedProfile(),
			'stats' => [
				'total_steps' => (int) $this->activities()->sum('steps'),
				'quests_done' => 0,
				'friends_count' => app(FriendshipService::class)->friendsCount($this->resource),
			],
		];
	}
}
