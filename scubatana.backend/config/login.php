<?php

return [

	'code_ttl_minutes' => (int) env('LOGIN_CODE_TTL_MINUTES', 10),

	'max_attempts' => (int) env('LOGIN_CODE_MAX_ATTEMPTS', 5),

	'requests_per_email_per_hour' => (int) env('LOGIN_CODE_REQUESTS_PER_EMAIL', 5),

	'requests_per_ip_per_hour' => (int) env('LOGIN_CODE_REQUESTS_PER_IP', 10),

	'verifications_per_ip_per_minute' => (int) env('LOGIN_CODE_VERIFICATIONS_PER_IP', 20),

	'minimum_age' => 15,

];
