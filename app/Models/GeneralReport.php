<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralReport extends Model
{
    protected $fillable = [
        'reporter_id',
        'reporter_type',
        'institution_id',
        'reported_to_role',
        'title',
        'description',
        'status',
        'response',
        'resolved_by_id',
        'resolved_by_type'
    ];

    public function reporter()
    {
        return $this->morphTo();
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function resolvedBy()
    {
        return $this->morphTo(__FUNCTION__, 'resolved_by_type', 'resolved_by_id');
    }
}
