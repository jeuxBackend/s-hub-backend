<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolTimetableBreakPeriod extends Model
{
    protected $fillable = [
        'config_id',
        'weekday',
        'name',
        'break_type',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'weekday' => 'integer',
    ];

    public function config()
    {
        return $this->belongsTo(SchoolTimetableConfig::class, 'config_id');
    }
}
