<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassroomStudentSubject extends Model
{
    protected $table = 'classroom_student_subject';

    protected $fillable = [
        'student_id',
        'classroom_id',
        'subject_id',
        'term',
        'academic_year',
        'created_by',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
