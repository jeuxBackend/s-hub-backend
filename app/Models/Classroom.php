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

    public function finalResultSubmissions()
    {
        return $this->hasMany(FinalResultSubmission::class);
    }
    
    // New relationship for assignments
    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }
}
