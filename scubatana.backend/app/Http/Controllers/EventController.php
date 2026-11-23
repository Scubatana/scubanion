<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Terminal;
use Illuminate\Http\JsonResponse;

class EventController extends Controller
{
	public function index(): JsonResponse
	{
		$now = now();
		$events = Event::notFinishedAt($now)
			->with(['accessory', 'terminals' => fn ($query) => $query->orderBy('name')])
			->orderBy('starts_at')
			->orderBy('id')
			->get()
			->map(fn (Event $event) => [
				'id' => $event->id,
				'name' => $event->name,
				'description' => $event->description,
				'starts_at' => $event->starts_at->toIso8601String(),
				'ends_at' => $event->ends_at->toIso8601String(),
				'in_progress' => $event->isInProgressAt($now),
				'bonus_xp' => $event->bonus_xp,
				'accessory' => $event->accessory?->active ? ['id' => $event->accessory->code, 'label' => $event->accessory->label] : null,
				'all_terminals' => $event->all_terminals,
				'terminals' => $event->all_terminals ? [] : $event->terminals->map(fn (Terminal $terminal) => ['code' => $terminal->code, 'name' => $terminal->name])->all(),
			]);

		return response()->json(['events' => $events]);
	}
}
