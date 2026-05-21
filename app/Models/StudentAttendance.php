<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAttendance extends Model
{
    protected $fillable = [
        'student_id',
        'classroom_id',
        'subject_id',
        'date',
        'status',
        'remarks',
    ];

    protected $casts = [
    'date' => 'date',
    'status' => \App\Enums\AttendanceStatus::class,
    ];

  

    // Relationships
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

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
