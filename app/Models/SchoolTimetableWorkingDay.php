<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolTimetableWorkingDay extends Model
{
    protected $fillable = [
        'config_id',
        'weekday',
        'is_open',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'is_open' => 'boolean',
    ];

    public function config()
    {
        return $this->belongsTo(SchoolTimetableConfig::class, 'config_id');
    }
}
