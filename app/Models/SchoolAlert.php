<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolAlert extends Model
{
    protected $fillable = [
        'institution_id',
        'created_by',
        'confirmed_by',
        'resolved_by',
        'type',
        'status',
        'title',
        'message',
        'confirmation_count',
        'confirmed_at',
        'resolved_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'confirmed_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function responses()
    {
        return $this->hasMany(SchoolAlertResponse::class);
    }
}
