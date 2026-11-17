<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Encounter;
use App\Models\Passage;
use App\Models\User;
use App\Models\UserPosition;
use App\Support\Geo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rencontres en vrai entre deux joueurs, prouvées sans Bluetooth : par le QR d'un joueur scanné
 * par l'autre quand leurs positions se recoupent, ou par un passage à la même borne à quelques minutes d'écart.
 */
class EncounterService
{
	private const INVALID_QR = "Ce QR code n'est plus valable. Demande à l'autre joueur de l'afficher à nouveau.";

	private const TOO_FAR = "Vous semblez trop loin l'un de l'autre : rapprochez-vous, puis scanne à nouveau.";

	public function __construct(
		private PositionService $positions,
		private QuestEngine $quests,
	) {}

	/**
	 * @param  array{latitude: float, longitude: float, accuracy: ?int, mocked: bool, recorded_at: CarbonImmutable}|null  $position
	 */
	public function meetByQr(User $scanner, ?User $shown, ?array $position): Encounter
	{
		if ($shown === null) {
			$this->refuse(self::INVALID_QR, 'token');
		}

		if ($shown->is($scanner)) {
			$this->refuse("C'est ton propre QR code : fais-le scanner par l'autre joueur.", 'token');
		}

		if (Block::between($scanner, $shown)->exists()) {
			$this->refuse(self::INVALID_QR, 'token');
		}

		if ($position !== null) {
			$this->positions->record($scanner, $position['latitude'], $position['longitude'], $position['accuracy'], $position['mocked'], $position['recorded_at']);
		}

		$mine = $this->ensureTogether($scanner, $shown);

		return $this->record($scanner, $shown, CarbonImmutable::now(), $this->zone($mine->latitude, $mine->longitude));
	}

	/**
	 * Crée une rencontre avec chaque joueur passé à la même borne à quelques minutes d'écart.
	 * Comme pour le QR, un joueur bloqué pour triche (position simulée, téléportation) ne rencontre personne.
	 *
	 * @return list<Encounter> les rencontres créées par ce passage
	 */
	public function meetAtTerminal(Passage $passage): array
	{
		$window = config('encounters.borne_window_minutes');
		$scannedAt = CarbonImmutable::instance($passage->scanned_at);
		$player = $passage->user;

		if ($player->isBannedFromLocationTasks()) {
			return [];
		}

		$place = $passage->terminal->place;
		$zone = $place === null ? null : $this->zone($place->latitude, $place->longitude);

		$otherIds = Passage::where('terminal_id', $passage->terminal_id)
			->where('user_id', '!=', $player->id)
			->whereBetween('scanned_at', [$scannedAt->subMinutes($window), $scannedAt->addMinutes($window)])
			->distinct()
			->pluck('user_id');
		$others = User::whereKey($otherIds)->orderBy('id')->get();

		$created = [];

		foreach ($others as $other) {
			if ($other->isBannedFromLocationTasks() || Block::between($player, $other)->exists()) {
				continue;
			}

			$encounter = $this->record($player, $other, $scannedAt, $zone);

			if ($encounter->wasRecentlyCreated) {
				$created[] = $encounter;
			}
		}

		return $created;
	}

	private function ensureTogether(User $scanner, User $shown): UserPosition
	{
		$since = now()->subMinutes(config('encounters.position_max_age_minutes'));
		$mine = $scanner->position()->first();
		$theirs = $shown->position()->first();

		if ($mine === null || $mine->recorded_at->lt($since)) {
			$this->refuse('Active ta localisation pour valider la rencontre, puis scanne à nouveau.', 'position');
		}

		if ($theirs === null || $theirs->recorded_at->lt($since)) {
			$this->refuse("Demande à {$shown->pseudo} d'ouvrir Scubanion avec sa localisation activée, puis scanne à nouveau.", 'position');
		}

		if ($mine->mocked || $theirs->mocked || $scanner->isBannedFromLocationTasks() || $shown->isBannedFromLocationTasks() || ! $this->close($mine, $theirs)) {
			$this->refuse(self::TOO_FAR, 'position');
		}

		return $mine;
	}

	private function close(UserPosition $mine, UserPosition $theirs): bool
	{
		$cap = config('places.max_accuracy_margin_m');
		$margin = min($mine->accuracy_m ?? 0, $cap) + min($theirs->accuracy_m ?? 0, $cap);

		return Geo::distanceInMeters($mine->latitude, $mine->longitude, $theirs->latitude, $theirs->longitude) <= config('encounters.max_distance_m') + $margin;
	}

	/**
	 * Une seule rencontre par paire de joueurs et par jour : la suivante renvoie celle du jour.
	 */
	private function record(User $player, User $other, CarbonImmutable $metAt, ?string $zone): Encounter
	{
		$encounter = DB::transaction(function () use ($player, $other, $metAt, $zone): Encounter {
			User::whereKey([$player->id, $other->id])->orderBy('id')->lockForUpdate()->get();

			$today = Encounter::between($player, $other)
				->whereBetween('met_at', [$metAt->startOfDay(), $metAt->endOfDay()])
				->first();

			return $today ?? Encounter::create([
				'user_id' => $player->id,
				'other_user_id' => $other->id,
				'met_at' => $metAt,
				'zone' => $zone,
				'validated_at' => now(),
			]);
		});

		if ($encounter->wasRecentlyCreated) {
			$this->quests->refreshPlayer($player);
			$this->quests->refreshPlayer($other);
		}

		return $encounter;
	}

	private function zone(float $latitude, float $longitude): string
	{
		return Geo::geohash($latitude, $longitude, config('encounters.zone_precision'));
	}

	private function refuse(string $message, string $field): never
	{
		throw ValidationException::withMessages([$field => $message]);
	}
}
