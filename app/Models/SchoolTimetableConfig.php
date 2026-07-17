<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolTimetableConfig extends Model
{
    protected $fillable = [
        'institution_id',
        'academic_year',
        'term',
        'mode',
        'school_start_time',
        'school_end_time',
        'lesson_duration_minutes',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'lesson_duration_minutes' => 'integer',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function workingDays()
    {
        return $this->hasMany(SchoolTimetableWorkingDay::class, 'config_id');
    }

    public function breakPeriods()
    {
        return $this->hasMany(SchoolTimetableBreakPeriod::class, 'config_id');
    }

    public function teacherAvailabilities()
    {
        return $this->hasMany(TeacherAvailability::class, 'config_id');
    }

    public function timetableEntries()
    {
        return $this->hasMany(TimetableEntry::class, 'config_id');
    }
}
