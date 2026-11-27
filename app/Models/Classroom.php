<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    protected $fillable = [
        'name',
        'code',
        'institution_id',
        'in_charge_id',
    ];

    public function inCharge()
    {
        return $this->belongsTo(User::class, 'in_charge_id');
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }
    
    public function teachers()
    {
        return $this->belongsToMany(User::class, 'classroom_teachers', 'classroom_id', 'teacher_id')
            ->using(\App\Models\ClassroomTeacher::class)
            ->withPivot(['assigned_by', 'term', 'year', 'section'])
            ->withTimestamps();
    }
    
    public function students()
    {
        return $this->hasMany(Student::class);
    }

    /**
     * Is this teacher assigned to the class in any way: allocated as class
     * teacher, set as the teacher of one of its subjects (rota requirements),
     * or placed on its timetable? Same definition the chat contacts use.
     */
    public function isTaughtBy(int $teacherId): bool
    {
        return $this->teachers()->where('users.id', $teacherId)->exists()
            || \App\Models\ClassSubjectRequirement::where('classroom_id', $this->id)
                ->where('is_active', true)
                ->where('teacher_id', $teacherId)
                ->exists()
            || \App\Models\TimetableEntry::where('classroom_id', $this->id)
                ->where('teacher_id', $teacherId)
                ->exists();
    }

    public function currentPreregistrations()
    {
        return $this->hasMany(StudentPreregistration::class, 'current_classroom_id');
    }

    public function targetPreregistrations()
    {
        return $this->hasMany(StudentPreregistration::class, 'target_classroom_id');
    }

    public function academicDocuments()
    {
        return $this->hasMany(AcademicDocument::class);
    }

    public function subjectDocuments()
    {
        return $this->hasMany(SubjectDocument::class);
    }

    public function finalResultSubmissions()
    {
        return $this->hasMany(FinalResultSubmission::class);
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

    public function classSubjectRequirements()
    {
        return $this->hasMany(ClassSubjectRequirement::class);
    }
}
