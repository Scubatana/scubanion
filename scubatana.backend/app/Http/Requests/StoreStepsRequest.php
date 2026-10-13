<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStepsRequest extends FormRequest
{
	public function rules(): array
	{
		$oldest = now()->subDays(config('steps.max_age_days'))->toDateString();
		$latest = now()->addDay()->toDateString();

		return [
			'device_id' => ['required', 'string', 'min:8', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
			'days' => ['required', 'array', 'min:1', 'max:'.(config('steps.max_age_days') + 2)],
			'days.*.day' => ['required', 'date_format:Y-m-d', 'distinct', 'after_or_equal:'.$oldest, 'before_or_equal:'.$latest],
			'days.*.steps' => ['required', 'integer', 'min:0', 'max:10000000'],
		];
	}
}
