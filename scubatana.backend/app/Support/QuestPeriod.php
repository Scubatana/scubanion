<?php

namespace App\Support;

use App\Enums\QuestRepeat;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class QuestPeriod
{
	public function __construct(
		public readonly string $key,
		public readonly ?CarbonImmutable $endsAt,
	) {}

	public static function at(QuestRepeat $repeat, CarbonInterface $moment): self
	{
		$moment = CarbonImmutable::instance($moment)->setTimezone(config('app.timezone'));

		return match ($repeat) {
			QuestRepeat::Once => new self('once', null),
			QuestRepeat::Daily => new self($moment->format('Y-m-d'), $moment->startOfDay()->addDay()),
			QuestRepeat::Weekly => new self($moment->format('o-\WW'), $moment->startOfWeek(CarbonInterface::MONDAY)->addWeek()),
		};
	}
}
