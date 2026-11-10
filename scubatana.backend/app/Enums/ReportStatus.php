<?php

namespace App\Enums;

enum ReportStatus: string
{
	case Pending = 'pending';
	case Reviewed = 'reviewed';
}
