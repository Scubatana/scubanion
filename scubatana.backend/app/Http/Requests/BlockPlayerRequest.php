<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlockPlayerRequest extends FormRequest
{
	public function rules(): array
	{
		return [
			'player_id' => [
				'required',
				'integer',
				Rule::exists('users', 'id')->where(fn ($query) => $query->whereNotNull('pseudo')),
				Rule::notIn([$this->user()->id]),
			],
		];
	}

	public function messages(): array
	{
		return [
			'player_id.required' => 'Indique le joueur à bloquer.',
			'player_id.exists' => "Ce joueur n'existe pas.",
			'player_id.not_in' => 'Tu ne peux pas te bloquer toi-même.',
		];
	}
}
