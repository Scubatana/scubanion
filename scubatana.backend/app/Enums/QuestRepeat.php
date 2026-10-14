<?php

namespace App\Enums;

enum QuestRepeat: string
{
	case Once = 'once';
	case Daily = 'daily';
	case Weekly = 'weekly';
}
