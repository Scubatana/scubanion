<?php

namespace App\Http\Requests;

use App\Enums\Ambition;
use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
	protected function prepareForValidation(): void
	{
		$this->merge([
			'pseudo' => trim((string) $this->input('pseudo')),
		]);
	}

	public function rules(): array
	{
		$latestBirthDate = now()->subYears(config('login.minimum_age'))->toDateString();

		return [
			'pseudo' => ['required', 'string', 'min:3', 'max:20', Rule::unique('users', 'pseudo')->ignore($this->user()->id)],
			'birth_date' => ['required', 'date_format:Y-m-d', 'after:1900-01-01', 'before_or_equal:'.$latestBirthDate],
			'gender' => ['nullable', Rule::enum(Gender::class)],
			'ambition' => ['required', Rule::enum(Ambition::class)],
		];
	}

	public function messages(): array
	{
		return [
			'pseudo.required' => 'Choisis un pseudo.',
			'pseudo.min' => 'Le pseudo doit faire au moins 3 caractères.',
			'pseudo.max' => 'Le pseudo doit faire au plus 20 caractères.',
			'pseudo.unique' => 'Ce pseudo est déjà pris.',
			'birth_date.required' => 'Indique ta date de naissance.',
			'birth_date.date_format' => 'La date de naissance est invalide.',
			'birth_date.after' => 'La date de naissance est invalide.',
			'birth_date.before_or_equal' => 'Tu dois avoir au moins '.config('login.minimum_age').' ans pour utiliser Scubanion.',
			'gender.enum' => 'Ce choix est invalide.',
			'ambition.required' => 'Choisis une ambition.',
			'ambition.enum' => 'Cette ambition est inconnue.',
		];
	}
}
