<?php

namespace App\Enums;

enum GuardianType: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Guardian = 'guardian';

    public static function options(): array
    {
        return array_map(
            fn(self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }

    public function label(): string
    {
        return match($this) {
            self::Father => 'Father',
            self::Mother => 'Mother',
            self::Guardian => 'Guardian',
        };
    }
}
