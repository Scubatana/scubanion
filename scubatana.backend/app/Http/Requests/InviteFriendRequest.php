<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InviteFriendRequest extends FormRequest
{
	public function rules(): array
	{
		return [
			'code' => ['required', 'string', 'max:32'],
		];
	}

	public function messages(): array
	{
		return [
			'code.required' => 'Indique le code de ton ami.',
		];
	}
}
