<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\TermType;

class StudentGrade extends Model
{
    protected $fillable = [
        'student_id',
        'classroom_id',
        'subject_id',
        'term',
        'score',
        'remarks',
        'type', 
        'date',
        'total',
        'recorded_by',
    ];

    protected $casts = [
        'term' => TermType::class,
        'date' => 'date', // cast to Carbon instance
    ];

    public $timestamps = false;

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function recordedBy() // ✅ Renamed to follow convention
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // Scopes
    public function scopeForTerm($query, $term)
    {
        return $query->where('term', $term);
    }

    public function scopeForSubject($query, $subjectId)
    {
        return $query->where('subject_id', $subjectId);
    }
}
