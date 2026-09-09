<?php

namespace App\Enums;

enum JournalVisibility: string
{
    case All = 'all';
    case MembersOnly = 'members_only';
}
