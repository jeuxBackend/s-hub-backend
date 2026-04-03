<?php

namespace App\Enums;

enum OtpType: string
{
    case Email = 'email';
    case Phone = 'phone';

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
            self::Email => 'Email',
            self::Phone => 'Phone',
        };
    }
}
