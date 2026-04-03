<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = ['name', 'code', 'classroom_id','institution_id'];

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }
    public function enrolledStudents()
    {
        return $this->belongsToMany(Student::class, 'classroom_student_subject')
            ->withPivot(['classroom_id', 'term', 'academic_year'])
            ->withTimestamps();
    }
}
