<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classname extends Model
{
    protected $fillable = [
        'institution_id',
        'name',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }
}
