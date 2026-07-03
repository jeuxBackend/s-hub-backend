<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolAlertResponse extends Model
{
    protected $fillable = [
        'school_alert_id',
        'institution_id',
        'user_id',
        'parent_user_id',
        'school_user_id',
        'student_id',
        'source_role',
        'parent_response_type',
        'school_response_type',
        'note',
        'meta',
        'responded_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'responded_at' => 'datetime',
    ];

    public function alert()
    {
        return $this->belongsTo(SchoolAlert::class, 'school_alert_id');
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parentUser()
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function schoolUser()
    {
        return $this->belongsTo(User::class, 'school_user_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
