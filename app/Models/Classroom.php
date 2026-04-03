<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    protected $fillable = [
        'name',
        'code',
        'institution_id',
    ];

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


}
