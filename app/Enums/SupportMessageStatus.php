<?php

namespace App\Enums;

enum SupportMessageStatus: string
{
    case New = 'new';
    case Responded = 'responded';
    case Resolved = 'resolved';
}
