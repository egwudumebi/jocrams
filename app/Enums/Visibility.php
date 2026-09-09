<?php

namespace App\Enums;

enum Visibility: string
{
    case Public = 'public';
    case MembersOnly = 'members_only';
    case TierSpecific = 'tier_specific';
    case Private = 'private';
}
