<?php

namespace App\Enums;

enum SchoolAssetType: string
{
    case Stamp = 'stamp';
    case Watermark = 'watermark';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(
            fn(self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases()
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Stamp => 'Stamp',
            self::Watermark => 'Watermark',
        };
    }
}
