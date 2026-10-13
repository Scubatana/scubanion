<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestCodeRequest extends FormRequest
{
	protected function prepareForValidation(): void
	{
		$this->merge([
			'email' => mb_strtolower(trim((string) $this->input('email'))),
		]);
	}

	public function rules(): array
	{
		return [
			'email' => ['required', 'email', 'max:255'],
		];
	}
}
