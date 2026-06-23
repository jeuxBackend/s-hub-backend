<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentPreregistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'guardian_id',
        'current_classroom_id',
        'target_classroom_id',
        'academic_year',
        'status',
        'submitted_at',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function guardian()
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }

    public function currentClassroom()
    {
        return $this->belongsTo(Classroom::class, 'current_classroom_id');
    }

    public function targetClassroom()
    {
        return $this->belongsTo(Classroom::class, 'target_classroom_id');
    }
}
