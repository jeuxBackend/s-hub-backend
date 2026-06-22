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
        'file_path',
        'file_original_name',
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

    // Accessor for file URL
    public function getFileUrlAttribute()
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }

    // Mutator for file storage
    public function setFile($file)
    {
        if ($file) {
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('grades', $filename, 'public');
            
            $this->file_path = $path;
            $this->file_original_name = $file->getClientOriginalName();
            
            return $this;
        }
        
        return $this;
    }
    
    protected $appends = ['file_url'];
}