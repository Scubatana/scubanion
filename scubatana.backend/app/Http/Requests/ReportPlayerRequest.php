<?php

namespace App\Http\Requests;

use App\Enums\ReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportPlayerRequest extends FormRequest
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
			'reason' => ['required', Rule::enum(ReportReason::class)],
			'comment' => ['nullable', 'string', 'max:500'],
		];
	}

	public function messages(): array
	{
		return [
			'player_id.required' => 'Indique le joueur à signaler.',
			'player_id.exists' => "Ce joueur n'existe pas.",
			'player_id.not_in' => 'Tu ne peux pas te signaler toi-même.',
			'reason.required' => 'Choisis le motif du signalement.',
			'reason.enum' => "Ce motif n'existe pas.",
			'comment.max' => 'Ton commentaire ne doit pas dépasser 500 caractères.',
		];
	}
}
