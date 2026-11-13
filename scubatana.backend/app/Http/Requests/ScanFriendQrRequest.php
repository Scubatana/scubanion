<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanFriendQrRequest extends FormRequest
{
	public function rules(): array
	{
		return [
			'token' => ['required', 'string', 'max:1000'],
		];
	}

	public function messages(): array
	{
		return [
			'token.required' => 'Scanne le QR code de ton ami.',
			'token.string' => "Ce QR code n'est plus valable. Demande à ton ami de l'afficher à nouveau.",
			'token.max' => "Ce QR code n'est plus valable. Demande à ton ami de l'afficher à nouveau.",
		];
	}
}
