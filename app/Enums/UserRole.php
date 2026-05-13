<?php

namespace App\Enums;

enum UserRole: string
{
    case Principal = 'principal';
    case Teacher = 'teacher';
    case Parent = 'parent';
    case SchoolAdmin = 'school-admin';

    /**
     * Get all role values as array (for migrations, validation, etc.)
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get options for dropdowns / forms
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
            self::Principal => 'Principal',
            self::Teacher => 'Teacher',
            self::Parent => 'Parent',
            self::SchoolAdmin => 'School Admin'
        };
    }
}