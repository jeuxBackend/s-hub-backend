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
        'country',
        'phone_number',
        'password',
        'role',
        'status',
        'permissions',
        'emergency_contact_name',
        'emergency_contact_phone',
        'fcm_token',
        'profile_image',
        'stripe_connect_account_id',
        'stripe_onboarding_completed',
        'password_changed_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'permissions' => 'array',
        'region' => 'array',
        'role' => \App\Enums\AdminRole::class,
        'stripe_onboarding_completed' => 'boolean',
        'password_changed_at' => 'datetime',
    ];

    public function getNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name . ' ' . $this->sure_name);
    }

    /**
     * The platform admin account can never be deleted, through any code
     * path — this fires on every delete()/destroy() call regardless of
     * which action/controller initiated it.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $admin) {
            if ($admin->role === \App\Enums\AdminRole::Admin) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'The platform admin account cannot be deleted.'
                );
            }
        });
    }

    public function institutions()
    {
        return $this->hasMany(Institution::class, 'manager_id');
    }

    /**
     * Schools this sub-admin has been specifically assigned to manage.
     */
    public function assignedSchools()
    {
        return $this->hasMany(Institution::class, 'subadmin_id');
    }

    /**
     * Institution IDs this sub-admin is restricted to, or null if
     * unrestricted (sees everything within their granted permissions).
     * Only ever restricts sub_admin — admin/manager are never limited here.
     *
     * Explicit per-school assignment always takes priority. Only when a
     * sub-admin has no explicit assignments at all do we fall back to every
     * school in their own country (if one is set on their own profile) —
     * this can legitimately return an empty array when that country simply
     * has no schools yet, which is intentional: stay restricted, don't fall
     * through to unrestricted. A sub-admin with neither an assignment nor a
     * country keeps the old unrestricted (null) behavior.
     */
    public function assignedInstitutionIds(): ?array
    {
        if ($this->role !== \App\Enums\AdminRole::SubAdmin) {
            return null;
        }

        $ids = $this->assignedSchools()->pluck('id')->all();

        if (!empty($ids)) {
            return $ids;
        }

        if ($this->country) {
            return Institution::where('country', $this->country)->pluck('id')->all();
        }

        return null;
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
