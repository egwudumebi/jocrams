<?php

namespace App\Enums;

enum JournalVolumeStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
