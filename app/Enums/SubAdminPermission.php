<?php

namespace App\Enums;

enum SubAdminPermission: string
{
    case Dashboard = 'Dashboard';
    case Schools = 'Schools';
    case Managers = 'Managers';
    case SchoolRequests = 'School_Requests';
    case Teachers = 'Teachers';
    case Students = 'Students';
    case Reports = 'Reports';
    case Settings = 'Settings';

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
