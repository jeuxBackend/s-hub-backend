<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // ✅ required
use App\Models\User;
use App\Models\Category;

class Institution extends Model
{
    protected $fillable = [
        'principal_id',
        'category_id',
        'name',
        'slogan',
        'logo',
        'academic_year',
        'examination_system',
        'physical_address',
        'email',
        'alternate_email',
        'phone_number',
        'alternate_phone',
        'telephone',
        'email_verified',
        'phone_verified',
        'subjects',
    ];

    protected $casts = [
        'email_verified' => 'boolean',
        'phone_verified' => 'boolean',
        'subjects'       => 'array',
    ];

    // ✅ Logo accessor
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : asset('defaults/user.png');;
    }

    // ✅ Principal (user)
    public function principal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'principal_id');
    }

    // ✅ Category
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
