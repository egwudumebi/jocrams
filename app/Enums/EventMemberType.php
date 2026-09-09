<?php

namespace App\Enums;

enum EventMemberType: string
{
    case Member = 'member';
    case NonMember = 'non_member';
    case Student = 'student';
    case Institution = 'institution';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return list<array{value: string, label: string}> */
    public static function catalog(): array
    {
        return [
            ['value' => self::Member->value, 'label' => 'Member'],
            ['value' => self::NonMember->value, 'label' => 'Non-member'],
            ['value' => self::Student->value, 'label' => 'Student'],
            ['value' => self::Institution->value, 'label' => 'Institution'],
        ];
    }
}
