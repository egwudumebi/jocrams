<?php

namespace App\Enums;

enum CredentialType: string
{
    case MembershipCard = 'membership_card';
    case Certificate = 'certificate';
    case WelcomeLetter = 'welcome_letter';
}
