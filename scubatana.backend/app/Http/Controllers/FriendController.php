<?php

namespace App\Http\Controllers;

use App\Enums\FriendshipStatus;
use App\Http\Requests\InviteFriendRequest;
use App\Models\Friendship;
use App\Models\User;
use App\Services\FriendshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FriendController extends Controller
{
	public function index(Request $request, FriendshipService $friends): JsonResponse
	{
		return response()->json($friends->overview($request->user()));
	}

	public function store(InviteFriendRequest $request, FriendshipService $friends): JsonResponse
	{
		$player = $request->user();

		if (! $player->hasCompletedProfile()) {
			return response()->json(['message' => "Complète ton profil avant d'ajouter des amis."], 409);
		}

		$friends->invite($player, $request->validated('code'));

		return response()->json($friends->overview($player));
	}

	public function accept(Request $request, Friendship $friendship, FriendshipService $friends): JsonResponse
	{
		$player = $request->user();
		$this->ensureInvolved($player, $friendship);

		if ($friendship->friend_id !== $player->id || $friendship->status !== FriendshipStatus::Pending) {
			return response()->json(['message' => 'Cette invitation ne peut pas être acceptée.'], 409);
		}

		$friends->accept($friendship);

		return response()->json($friends->overview($player));
	}

	public function destroy(Request $request, Friendship $friendship, FriendshipService $friends): JsonResponse
	{
		$player = $request->user();
		$this->ensureInvolved($player, $friendship);

		$friendship->delete();

		return response()->json($friends->overview($player));
	}

	private function ensureInvolved(User $player, Friendship $friendship): void
	{
		abort_unless($friendship->user_id === $player->id || $friendship->friend_id === $player->id, 404);
	}
}
