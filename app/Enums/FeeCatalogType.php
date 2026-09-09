<?php

namespace App\Enums;

enum FeeCatalogType: string
{
    case AnnualDues = 'annual_dues';
    case Submission = 'submission';
    case Publication = 'publication';
    case Registration = 'registration';
    case EventRegistration = 'event_registration';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return list<string> */
    public static function forCategory(FeeCatalogCategory $category): array
    {
        return match ($category) {
            FeeCatalogCategory::Membership => [
                self::AnnualDues->value,
                self::Registration->value,
                self::Other->value,
            ],
            FeeCatalogCategory::Journal => [
                self::Submission->value,
                self::Publication->value,
                self::Other->value,
            ],
            FeeCatalogCategory::Event => [
                self::EventRegistration->value,
                self::Registration->value,
                self::Other->value,
            ],
        };
    }
}
