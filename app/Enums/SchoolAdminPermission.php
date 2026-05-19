<?php

namespace App\Enums;

enum SchoolAdminPermission: string
{
    case Teachers = 'Teachers';
    case Students = 'Students';
    case Parents = 'Parents';
    case Classrooms = 'Classrooms';
    case Subjects = 'Subjects';
    case Finance = 'Finance';
    case Dashboard = 'Dashboard';

    /**
     * Get all permission values as array
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
                'label' => $case->value,
            ],
            self::cases()
        );
    }
}
