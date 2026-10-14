<?php

namespace App\Enums;

enum AppAction: string
{
	case Auto = 'auto';
	case Checkin = 'checkin';
	case Scan = 'scan';
	case Answer = 'answer';
	case Meet = 'meet';
}
