<?php

namespace App\Services;

use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;
use App\Support\FriendCode;
use Illuminate\Validation\ValidationException;

class FriendshipService
{
	public function __construct(private StepService $steps) {}

	public function invite(User $player, string $code): Friendship
	{
		$other = User::where('friend_code', FriendCode::normalize($code))->whereNotNull('pseudo')->first();

		if ($other === null) {
			$this->refuse("Aucun joueur n'a ce code.");
		}

		if ($other->is($player)) {
			$this->refuse("C'est ton propre code : partage-le à tes amis.");
		}

		$existing = Friendship::between($player, $other)->first();

		if ($existing?->status === FriendshipStatus::Accepted) {
			$this->refuse('Vous êtes déjà amis.');
		}

		if ($existing !== null && $existing->friend_id === $player->id) {
			return $this->accept($existing);
		}

		if ($existing !== null) {
			$this->refuse('Invitation déjà envoyée.');
		}

		return Friendship::create(['user_id' => $player->id, 'friend_id' => $other->id, 'status' => FriendshipStatus::Pending]);
	}

	public function accept(Friendship $friendship): Friendship
	{
		$friendship->update(['status' => FriendshipStatus::Accepted, 'accepted_at' => now()]);

		return $friendship;
	}

	public function overview(User $player): array
	{
		$friendships = Friendship::involving($player)->with(['requester.companion', 'invitee.companion'])->orderBy('id')->get();
		$pending = $friendships->where('status', FriendshipStatus::Pending);

		return [
			'code' => $player->friend_code,
			'friends' => $friendships->where('status', FriendshipStatus::Accepted)->map(fn (Friendship $friendship) => [
				'id' => $friendship->id,
				'since' => $friendship->accepted_at->toDateString(),
				'player' => $this->friendCard($friendship->otherThan($player)),
			])->values()->all(),
			'received' => $pending->where('friend_id', $player->id)->map(fn (Friendship $friendship) => $this->invitation($friendship, $friendship->requester))->values()->all(),
			'sent' => $pending->where('user_id', $player->id)->map(fn (Friendship $friendship) => $this->invitation($friendship, $friendship->invitee))->values()->all(),
		];
	}

	public function friendsCount(User $player): int
	{
		return Friendship::involving($player)->accepted()->count();
	}

	private function friendCard(User $friend): array
	{
		$companion = $friend->companion;

		return [
			'id' => $friend->id,
			'pseudo' => $friend->pseudo,
			'companion' => $companion === null ? null : [
				'name' => $companion->name,
				'species' => $companion->species,
				'color' => $companion->color,
				'accessories' => $companion->accessories ?? [],
				'level' => $companion->level,
			],
			'today' => $this->steps->today($friend),
			'streak' => $this->steps->streak($friend),
		];
	}

	private function invitation(Friendship $friendship, User $other): array
	{
		return [
			'id' => $friendship->id,
			'sent_at' => $friendship->created_at->toIso8601String(),
			'player' => ['id' => $other->id, 'pseudo' => $other->pseudo],
		];
	}

	private function refuse(string $message): never
	{
		throw ValidationException::withMessages(['code' => $message]);
	}
}
