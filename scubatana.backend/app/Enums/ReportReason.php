<?php

namespace App\Enums;

enum ReportReason: string
{
	case Pseudo = 'pseudo';
	case Behavior = 'behavior';
	case Other = 'other';
}
