<?php

return [

	'max_per_hour' => (int) env('STEPS_MAX_PER_HOUR', 12000),

	'max_per_day' => (int) env('STEPS_MAX_PER_DAY', 100000),

	'max_age_days' => (int) env('STEPS_MAX_AGE_DAYS', 30),

	'min_goal' => 1000,

	'max_goal' => 50000,

];
