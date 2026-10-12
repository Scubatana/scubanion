<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyCodeRequest extends FormRequest
{
	protected function prepareForValidation(): void
	{
		$this->merge([
			'email' => mb_strtolower(trim((string) $this->input('email'))),
			'code' => preg_replace('/\D+/', '', (string) $this->input('code')),
		]);
	}

	public function rules(): array
	{
		return [
			'email' => ['required', 'email', 'max:255'],
			'code' => ['required', 'digits:6'],
			'terms_accepted' => ['sometimes', 'boolean'],
		];
	}
}
