<?php

namespace App\Models;

use App\Enums\TermType;
use Illuminate\Database\Eloquent\Model;

class FinalResultSubmission extends Model
{
    protected $table = 'final_results_submissions';

    protected $fillable = [
        'institution_id',
        'classroom_id',
        'term',
        'title',
        'is_active',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'term' => TermType::class,
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
