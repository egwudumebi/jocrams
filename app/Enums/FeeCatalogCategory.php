<?php

namespace App\Enums;

enum FeeCatalogCategory: string
{
    case Membership = 'membership';
    case Journal = 'journal';
    case Event = 'event';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
