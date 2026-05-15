<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledTuitionUpdate extends Model
{
    protected $fillable = [
        'institution_id',
        'classroom_id',
        'year',
        'semester',
        'frequency',
        'is_active',
        'last_sent_at',
        'created_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
