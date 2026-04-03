<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case SubAdmin = 'sub_admin';
    case Principal = 'principal';
    case Teacher = 'teacher';
    case Parent = 'parent';
    case SchoolAdmin = 'school_admin'; // ✅ NEW

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
            self::Admin        => 'Admin',
            self::SubAdmin     => 'Sub Admin',
            self::Principal    => 'Principal',
            self::Teacher      => 'Teacher',
            self::Parent       => 'Parent',
            self::SchoolAdmin  => 'School Admin', // ✅ NEW
        };
    }
}
