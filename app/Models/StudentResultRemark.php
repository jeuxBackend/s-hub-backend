<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentResultRemark extends Model
{
    protected $fillable = [
        'student_id',
        'classroom_id',
        'term',
        'conduct',
        'possible_attendance',
        'times_absent',
        'times_late',
        'teacher_remarks',
        'head_teacher_remarks',
        'next_term_begins',
        'updated_by',
    ];

    protected $casts = [
        'next_term_begins' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }
}
