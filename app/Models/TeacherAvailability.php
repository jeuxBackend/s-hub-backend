<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherAvailability extends Model
{
    protected $fillable = [
        'institution_id',
        'teacher_id',
        'config_id',
        'weekday',
        'start_time',
        'end_time',
        'availability_type',
    ];

    protected $casts = [
        'weekday' => 'integer',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function config()
    {
        return $this->belongsTo(SchoolTimetableConfig::class, 'config_id');
    }
}
