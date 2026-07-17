<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\Category;
use App\Models\Admin;

class Institution extends Model
{
    protected $fillable = [
        'manager_id',
        'subadmin_id',
        'category_id',
        'status',
        'name',
        'slogan',
        'logo',
        'academic_year',
        'examination_system',
        'physical_address',
        'region',
        'email',
        'alternate_email',
        'phone_number',
        'alternate_phone',
        'telephone',
        'email_verified',
        'phone_verified',
        'alert_feature_enabled',
        'allowed_alert_types',
        'subjects',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'email_verified' => 'boolean',
        'phone_verified' => 'boolean',
        'alert_feature_enabled' => 'boolean',
        'allowed_alert_types' => 'array',
        'subjects' => 'array',
    ];

    public function getLogoAttribute($value): ?string
    {
        return $value
            ? asset('storage/' . $value)
            : asset('defaults/user.png');
    }

    public function principal()
    {
        return $this->hasOne(User::class, 'institution_id')->where('role', \App\Enums\UserRole::Principal->value);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'manager_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function timetableConfigs()
    {
        return $this->hasMany(SchoolTimetableConfig::class);
    }

    public function teacherAvailabilities()
    {
        return $this->hasMany(TeacherAvailability::class);
    }

    public function classSubjectRequirements()
    {
        return $this->hasMany(ClassSubjectRequirement::class);
    }
}
