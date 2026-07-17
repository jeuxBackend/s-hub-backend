<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = [
        'name',
        'code',
        'classroom_id',
        'institution_id',
        'teacher_id',
        'lectures_per_week',
        'start_time',
        'end_time',
        'is_proxy',
        'proxy_teacher_id',
        'proxy_start_time',
        'proxy_end_time',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }
    
    public function enrolledStudents()
    {
        return $this->belongsToMany(Student::class, 'classroom_student_subject')
            ->withPivot(['classroom_id', 'term', 'academic_year'])
            ->withTimestamps();
    }
    
    // New relationship for assignments
    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function timetableEntries()
    {
        return $this->hasMany(TimetableEntry::class);
    }

    public function classSubjectRequirement()
    {
        return $this->hasOne(ClassSubjectRequirement::class);
    }

    public function effectiveTeacher()
    {
        return $this->classSubjectRequirement?->teacher;
    }

    public function subjectDocuments()
    {
        return $this->hasMany(SubjectDocument::class);
    }
}
