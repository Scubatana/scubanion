<?php

return [

	'max_distance_m' => (int) env('ENCOUNTER_MAX_DISTANCE_M', 100),

	'position_max_age_minutes' => (int) env('ENCOUNTER_POSITION_MAX_AGE_MINUTES', 10),

	'borne_window_minutes' => (int) env('ENCOUNTER_BORNE_WINDOW_MINUTES', 10),

	'zone_precision' => 6,

	'zone_retention_days' => (int) env('ENCOUNTER_ZONE_RETENTION_DAYS', 30),

];
