<?php

namespace App\Enums;

enum TermType: string
{
    case First = 'first';
    case Second = 'second';
    case Third = 'third';
    case Final = 'final';
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
