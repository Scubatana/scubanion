<?php

namespace App\Http\Requests;

use App\Services\StepService;
use Illuminate\Foundation\Http\FormRequest;

class ShowStepsRequest extends FormRequest
{
	public function rules(): array
	{
		return [
			'month' => [
				'nullable',
				'date_format:Y-m',
				'after_or_equal:'.app(StepService::class)->firstMonth($this->user())->format('Y-m'),
				'before_or_equal:'.now()->format('Y-m'),
			],
		];
	}
}
