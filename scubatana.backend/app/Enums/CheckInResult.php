<?php

namespace App\Enums;

enum CheckInResult
{
	case Validated;
	case NotThere;
	case AlreadyVisited;
}
