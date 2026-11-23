<?php

namespace App\Http\Controllers;

use App\Http\Requests\MeetPlayerRequest;
use App\Services\EncounterService;
use App\Services\FriendQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EncounterController extends Controller
{
	private const PROFILE_REQUIRED = 'Complète ton profil avant de valider une rencontre.';

	// QR de rencontre : distinct du QR d'ami, il ne permet pas à celui qui le scanne de devenir ami.
	public function qr(Request $request, FriendQrService $qr): JsonResponse
	{
		$player = $request->user();

		if (! $player->hasCompletedProfile()) {
			return response()->json(['message' => self::PROFILE_REQUIRED], 409);
		}

		return response()->json($qr->issue($player, FriendQrService::MEET));
	}

	public function store(MeetPlayerRequest $request, FriendQrService $qr, EncounterService $encounters): JsonResponse
	{
		$player = $request->user();

		if (! $player->hasCompletedProfile()) {
			return response()->json(['message' => self::PROFILE_REQUIRED], 409);
		}

		$encounter = $encounters->meetByQr($player, $qr->resolve($request->validated('token'), FriendQrService::MEET), $request->position());
		$other = $encounter->user_id === $player->id ? $encounter->otherUser : $encounter->user;

		return response()->json([
			'player' => ['pseudo' => $other->pseudo],
			'met_at' => $encounter->met_at->toIso8601String(),
			'new' => $encounter->wasRecentlyCreated,
		], $encounter->wasRecentlyCreated ? 201 : 200);
	}
}
