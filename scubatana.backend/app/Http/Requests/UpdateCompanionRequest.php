<?php

namespace App\Http\Requests;

use App\Services\CompanionCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCompanionRequest extends FormRequest
{
	protected function prepareForValidation(): void
	{
		$this->merge([
			'name' => trim((string) $this->input('name')),
		]);
	}

	public function rules(): array
	{
		$catalog = app(CompanionCatalogService::class);

		return [
			'name' => ['required', 'string', 'min:2', 'max:20'],
			'species' => ['required', Rule::in($catalog->speciesIds())],
			'color' => ['required', Rule::in($catalog->colorIds())],
			'accessories' => ['present', 'array'],
			'accessories.*' => ['string', 'distinct', Rule::in([...$catalog->accessoryIds(), ...$this->wornAccessories()])],
		];
	}

	public function after(): array
	{
		return [
			function (Validator $validator): void {
				if ($validator->errors()->isNotEmpty()) {
					return;
				}

				$catalog = app(CompanionCatalogService::class);
				$accessories = $this->input('accessories', []);

				if ($catalog->conflictingAccessories($accessories) !== []) {
					$validator->errors()->add('accessories', 'Un seul accessoire par catégorie.');
				}

				$locked = array_diff(array_intersect($accessories, $catalog->earnableAccessoryIds()), $this->user()->ownedAccessoryCodes(), $this->wornAccessories());

				if ($locked !== []) {
					$validator->errors()->add('accessories', 'Cet accessoire se gagne en terminant une quête.');
				}
			},
		];
	}

	private function wornAccessories(): array
	{
		return $this->user()->companion?->accessories ?? [];
	}

	public function messages(): array
	{
		return [
			'name.required' => 'Donne un nom à ton compagnon.',
			'name.min' => 'Le nom doit faire au moins 2 caractères.',
			'name.max' => 'Le nom doit faire au plus 20 caractères.',
			'species.in' => 'Cette base est inconnue.',
			'color.in' => 'Cette couleur est inconnue.',
			'accessories.*.in' => 'Cet accessoire est inconnu.',
			'accessories.*.distinct' => 'Un accessoire ne se porte qu\'une fois.',
		];
	}
}
