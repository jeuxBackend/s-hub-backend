<?php

namespace App\Enums;

enum AdminRole: string
{
    case Admin = 'admin';
    case SubAdmin = 'sub_admin';
    case Manager = 'manager';

    /**
     * Get all role values as array (Required for migration)
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get options for dropdowns / Filament / Forms
     */
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

    /**
     * Human readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::SubAdmin => 'Sub Admin',
            self::Manager => 'Manager',
        };
    }
}