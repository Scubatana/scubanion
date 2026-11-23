<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class MeetPlayerRequest extends FormRequest
{
	private const CLOCK_TOLERANCE_MINUTES = 5;

	private const INVALID = "Ce QR code n'est plus valable. Demande à l'autre joueur de l'afficher à nouveau.";

	public function rules(): array
	{
		return [
			'token' => ['required', 'string', 'max:1000'],
			'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
			'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
			'accuracy_m' => ['nullable', 'numeric', 'min:0'],
			'mocked' => ['sometimes', 'boolean'],
			'recorded_at' => ['nullable', 'date', 'before_or_equal:'.now()->addMinutes(self::CLOCK_TOLERANCE_MINUTES)->toIso8601String()],
		];
	}

	public function messages(): array
	{
		return [
			'token.required' => "Scanne le QR code de l'autre joueur.",
			'token.string' => self::INVALID,
			'token.max' => self::INVALID,
		];
	}

	/**
	 * @return array{latitude: float, longitude: float, accuracy: ?int, mocked: bool, recorded_at: CarbonImmutable}|null
	 */
	public function position(): ?array
	{
		$data = $this->validated();

		if (! isset($data['latitude'])) {
			return null;
		}

		return [
			'latitude' => (float) $data['latitude'],
			'longitude' => (float) $data['longitude'],
			'accuracy' => isset($data['accuracy_m']) ? (int) round((float) $data['accuracy_m']) : null,
			'mocked' => $this->boolean('mocked'),
			'recorded_at' => isset($data['recorded_at']) ? CarbonImmutable::parse($data['recorded_at'])->setTimezone(config('app.timezone')) : CarbonImmutable::now(),
		];
	}
}
