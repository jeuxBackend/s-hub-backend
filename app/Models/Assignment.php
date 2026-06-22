<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\UserRole;

class Assignment extends Model
{
    protected $fillable = [
        'title',
        'assignment_text',
        'classroom_id',
        'subject_id',
        'teacher_id',
        'status',
        'file_path',
        'file_original_name',
        'submission_end_date',
        'assignment_date',
    ];

    protected $casts = [
        'submission_end_date' => 'datetime',
        'assignment_date' => 'datetime',
    ];

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

    public function students()
    {
        return $this->belongsToMany(Student::class, 'assignment_submissions')
                    ->withPivot(['submitted_at', 'file_path', 'file_original_name', 'score', 'feedback'])
                    ->withTimestamps();
    }

    public function submissions()
    {
        return $this->hasMany(AssignmentSubmission::class);
    }
}