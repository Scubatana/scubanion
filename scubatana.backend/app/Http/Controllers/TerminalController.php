<?php

namespace App\Http\Controllers;

use App\Models\Terminal;
use Illuminate\Http\JsonResponse;

class TerminalController extends Controller
{
	public function index(): JsonResponse
	{
		$terminals = Terminal::with('place')->orderBy('name')->get()->map(fn (Terminal $terminal) => [
			'code' => $terminal->code,
			'name' => $terminal->name,
			'online' => $terminal->isOnline(),
			'last_seen_at' => $terminal->last_seen_at?->toIso8601String(),
			'place' => $terminal->place === null ? null : [
				'id' => $terminal->place->id,
				'name' => $terminal->place->name,
				'latitude' => $terminal->place->latitude,
				'longitude' => $terminal->place->longitude,
			],
		]);

		return response()->json(['terminals' => $terminals]);
	}
}
