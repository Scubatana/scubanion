<?php

namespace App\Enums;

enum QuestType: string
{
	case Steps = 'steps';
	case Place = 'place';
	case Encounter = 'encounter';
	case Quiz = 'quiz';
	case Photo = 'photo';
}
