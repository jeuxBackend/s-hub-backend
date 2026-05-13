<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // ✅ required
use App\Models\User;
use App\Models\Category;
use App\Models\Admin;

class Institution extends Model
{
    protected $fillable = [
        'manager_id',
        'subadmin_id',
        'category_id',
        'status',
        'name',
        'slogan',
        'logo',
        'academic_year',
        'examination_system',
        'physical_address',
        'region',
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
        'subjects' => 'array',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : asset('defaults/user.png');
        ;
    }

    public function principal()
    {
        return $this->hasOne(User::class, 'institution_id')->where('role', \App\Enums\UserRole::Principal->value);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'manager_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
