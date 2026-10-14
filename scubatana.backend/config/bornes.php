<?php

return [

	'mqtt' => [
		'host' => env('MQTT_HOST', 'mosquitto'),
		'port' => (int) env('MQTT_PORT', 1883),
		'username' => env('MQTT_USERNAME'),
		'password' => env('MQTT_PASSWORD'),
		'client_id' => env('MQTT_CLIENT_ID', 'scubanion-api'),
	],

	'topic_prefix' => 'scubanion/bornes',

	'reward_xp' => (int) env('BORNE_REWARD_XP', 100),

	'max_replay_hours' => (int) env('BORNE_MAX_REPLAY_HOURS', 24),

	'future_tolerance_minutes' => (int) env('BORNE_FUTURE_TOLERANCE_MINUTES', 5),

	'offline_after_seconds' => (int) env('BORNE_OFFLINE_AFTER_SECONDS', 120),

];
