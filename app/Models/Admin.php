<?php

namespace App\Models;

use App\Traits\HasPermissions;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'admins';

    protected $fillable = [
        'first_name',
        'sure_name',
        'last_name',
        'email',
        'region',
        'phone_number',
        'password',
        'role',
        'status',
        'permissions',
        'fcm_token',
        'profile_image',
        'stripe_connect_account_id',
        'stripe_onboarding_completed',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'permissions' => 'array',
        'region' => 'array',
        'role' => \App\Enums\AdminRole::class,
        'stripe_onboarding_completed' => 'boolean',
    ];

    public function getNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name . ' ' . $this->sure_name);
    }

    public function institutions()
    {
        return $this->hasMany(Institution::class, 'manager_id');
    }

    public function students()
    {
        return $this->hasManyThrough(Student::class, Institution::class, 'manager_id', 'institution_id');
    }

    public function users()
    {
        return $this->hasManyThrough(User::class, Institution::class, 'manager_id', 'institution_id');
    }

    public function invoices()
    {
        return $this->hasMany(ManagerInvoice::class);
    }

}
