<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstituteType extends Model
{
    use HasFactory;

    protected $table = 'institute_types';

    protected $fillable = [
        'type',
    ];

    /**
     * Appends accessors to array / JSON representation.
     */
    protected $appends = [
        'type_name',
    ];

    /**
     * Alias for type_name to support both naming styles.
     */
    public function getTypeNameAttribute(): ?string
    {
        return $this->type;
    }

    public function setTypeNameAttribute(?string $value): void
    {
        $this->attributes['type'] = $value;
    }
}
