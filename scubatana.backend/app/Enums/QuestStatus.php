<?php

namespace App\Enums;

enum QuestStatus: string
{
	case Draft = 'draft';
	case Published = 'published';
	case Archived = 'archived';
}
