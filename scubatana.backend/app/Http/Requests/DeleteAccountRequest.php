<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteAccountRequest extends FormRequest
{
	public function rules(): array
	{
		return [
			'confirm' => ['accepted'],
		];
	}

	public function messages(): array
	{
		return [
			'confirm.accepted' => 'Confirme la suppression de ton compte.',
		];
	}
}
