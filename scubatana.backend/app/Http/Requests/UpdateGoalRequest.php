<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGoalRequest extends FormRequest
{
	public function rules(): array
	{
		return [
			'daily_step_goal' => ['required', 'integer', 'min:'.config('steps.min_goal'), 'max:'.config('steps.max_goal')],
		];
	}
}
