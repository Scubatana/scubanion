<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrivacyRequest extends FormRequest
{
	public function rules(): array
	{
		return [
			'share_steps' => ['required', 'boolean'],
			'share_streak' => ['required', 'boolean'],
		];
	}

	public function messages(): array
	{
		return [
			'share_steps.required' => 'Indique si tu souhaites partager tes pas.',
			'share_steps.boolean' => 'Ce choix doit être vrai ou faux.',
			'share_streak.required' => 'Indique si tu souhaites partager ta série.',
			'share_streak.boolean' => 'Ce choix doit être vrai ou faux.',
		];
	}
}
