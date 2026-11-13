<?php

namespace App\Http\Controllers;

use App\Http\Requests\BlockPlayerRequest;
use App\Models\Block;
use App\Models\User;
use App\Services\BlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlockController extends Controller
{
	public function index(Request $request, BlockService $blocks): JsonResponse
	{
		return response()->json($blocks->overview($request->user()));
	}

	public function store(BlockPlayerRequest $request, BlockService $blocks): JsonResponse
	{
		$player = $request->user();

		$blocks->block($player, User::findOrFail($request->validated('player_id')));

		return response()->json($blocks->overview($player));
	}

	public function destroy(Request $request, Block $block, BlockService $blocks): JsonResponse
	{
		$player = $request->user();
		abort_unless($block->user_id === $player->id, 404);

		$block->delete();

		return response()->json($blocks->overview($player));
	}
}
