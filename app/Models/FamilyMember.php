<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FamilyMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'first_name',
        'sur_name',
        'email',
        'phone_number',
        'address',
        'relation_with_parent',
        'profile_picture',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function getProfilePictureAttribute($value): string
    {
        if (!$value) {
            return asset('defaults/user.png');
        }

        if (str_contains($value, '/')) {
            return asset('storage/' . $value);
        }

        return asset('candidatefiles/' . $value);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->sur_name}");
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }
}
