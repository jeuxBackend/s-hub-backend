<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Rejected = 'rejected';
    case Closed = 'closed';

    /**
     * Get all status values as array
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

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Resolved => 'Resolved',
            self::Rejected => 'Rejected',
            self::Closed => 'Closed',
        };
    }
}
