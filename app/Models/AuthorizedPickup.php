<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthorizedPickup extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'first_name',
        'last_name',
        'sur_name',
        'relationship',
        'phone_number',
        'address',
    ];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name} {$this->sur_name}");
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }
}
