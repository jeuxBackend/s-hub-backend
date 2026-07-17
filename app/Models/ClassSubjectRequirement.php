<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassSubjectRequirement extends Model
{
    protected $fillable = [
        'institution_id',
        'classroom_id',
        'subject_id',
        'teacher_id',
        'lessons_per_week',
        'double_period_allowed',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'lessons_per_week' => 'integer',
        'double_period_allowed' => 'boolean',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
