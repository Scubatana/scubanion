<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BlockService
{
	public function block(User $player, User $other): Block
	{
		// Même verrou que FriendshipService::addByQr() : un scan simultané attend la fin du blocage,
		// puis le voit et est refusé, au lieu de recréer une amitié à côté d'un blocage actif.
		return DB::transaction(function () use ($player, $other): Block {
			User::whereKey([$player->id, $other->id])->orderBy('id')->lockForUpdate()->get();
			Friendship::between($player, $other)->delete();

			return Block::firstOrCreate(['user_id' => $player->id, 'blocked_id' => $other->id]);
		});
	}

	public function overview(User $player): array
	{
		$blocks = Block::where('user_id', $player->id)->with('blocked')->orderBy('id')->get();

		return [
			'blocked' => $blocks->map(fn (Block $block) => [
				'id' => $block->id,
				'since' => $block->created_at->toDateString(),
				'player' => ['id' => $block->blocked->id, 'pseudo' => $block->blocked->pseudo],
			])->values()->all(),
		];
	}
}
