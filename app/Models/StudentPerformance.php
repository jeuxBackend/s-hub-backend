<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Student;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\Term;
use App\Models\Session;
use App\Models\Teacher;

class StudentPerformance extends Model
{
    protected $table = "student_performances";

    protected $fillable = [
        'student_id',
        'class_id',
        'subject_id',
        'term_id',
        'session_id',
        'teacher_id',
        'total_mark',
        'obtained_mark',
        'remarks',
        'result_sheet',
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

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
}
