<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SubjectDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'classroom_id',
        'subject_id',
        'teacher_id',
        'document_type',
        'title',
        'description',
        'file_path',
        'file_original_name',
        'mime_type',
        'file_size',
        'academic_year',
        'term',
    ];

    protected $appends = ['file_url'];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }
}
