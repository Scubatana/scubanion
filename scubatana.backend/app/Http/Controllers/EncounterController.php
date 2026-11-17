<?php

namespace App\Http\Controllers;

use App\Http\Requests\MeetPlayerRequest;
use App\Services\EncounterService;
use App\Services\FriendQrService;
use Illuminate\Http\JsonResponse;

class EncounterController extends Controller
{
	public function store(MeetPlayerRequest $request, FriendQrService $qr, EncounterService $encounters): JsonResponse
	{
		$player = $request->user();

		if (! $player->hasCompletedProfile()) {
			return response()->json(['message' => 'Complète ton profil avant de valider une rencontre.'], 409);
		}

		$encounter = $encounters->meetByQr($player, $qr->resolve($request->validated('token')), $request->position());
		$other = $encounter->user_id === $player->id ? $encounter->otherUser : $encounter->user;

		return response()->json([
			'player' => ['pseudo' => $other->pseudo],
			'met_at' => $encounter->met_at->toIso8601String(),
			'new' => $encounter->wasRecentlyCreated,
		], $encounter->wasRecentlyCreated ? 201 : 200);
	}
}
