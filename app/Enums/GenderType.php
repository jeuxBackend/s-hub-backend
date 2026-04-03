<?php

namespace App\Enums;

enum GenderType: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other'; // optional

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
