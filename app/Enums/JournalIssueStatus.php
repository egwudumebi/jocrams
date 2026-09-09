<?php

namespace App\Enums;

enum JournalIssueStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case Published = 'published';
}
