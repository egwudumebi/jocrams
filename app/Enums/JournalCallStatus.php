<?php

namespace App\Enums;

enum JournalCallStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
}
