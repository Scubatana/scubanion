<?php

return [

	'teleport_speed_kmh' => (int) env('TELEPORT_SPEED_KMH', 1000),

	'teleport_min_distance_m' => (int) env('TELEPORT_MIN_DISTANCE_M', 5000),

	'ban_hours' => (int) env('QUEST_BAN_HOURS', 24),

	'map_max_span_degrees' => 0.5,

	'map_max_places' => 500,

];
