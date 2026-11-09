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
		return DB::transaction(function () use ($player, $other): Block {
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
