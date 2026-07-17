<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableEntry extends Model
{
    protected $fillable = [
        'institution_id',
        'config_id',
        'academic_year',
        'term',
        'subject_id',
        'classroom_id',
        'teacher_id',
        'weekday',
        'period_number',
        'start_time',
        'end_time',
        'entry_type',
        'version',
        'is_locked',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'period_number' => 'integer',
        'version' => 'integer',
        'is_locked' => 'boolean',
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function config()
    {
        return $this->belongsTo(SchoolTimetableConfig::class, 'config_id');
    }
}
