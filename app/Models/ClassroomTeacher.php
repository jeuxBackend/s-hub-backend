<?php

namespace App\Models;
use App\Enums\TermType;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ClassroomTeacher extends Pivot
{
    protected $table = 'classroom_teachers';

    protected $fillable = [
        'classroom_id',
        'teacher_id',
        'assigned_by',
        'term',
        'year',
        'section',
    ];

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
   

    protected function casts(): array
    {
        return [
            'term' => TermType::class,
        ];
    }
}
