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
        'state',
        'province',
        'city',
        'phone_number',
        'password',
        'role',
        'manager_id',
        'parent_id',
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

    /**
     * Countries are stored as 2-letter codes ("gm" -> "GM"), like schools and
     * users, so filters, sub-admin scoping and Stripe all see one format.
     * Any other legacy text is left as it is.
     */
    public function setCountryAttribute($value): void
    {
        $this->attributes['country'] = is_string($value) && preg_match('/^\s*[A-Za-z]{2}\s*$/', $value)
            ? strtoupper(trim($value))
            : $value;
    }

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

    /**
     * Column on `institutions` that ties a school to this account:
     * managers own schools via manager_id, sub-managers are assigned
     * schools via sub_manager_id.
     */
    public function institutionOwnerColumn(): string
    {
        return $this->role === \App\Enums\AdminRole::SubManager ? 'sub_manager_id' : 'manager_id';
    }

    /**
     * True for the two accounts that work through the /manager routes.
     */
    public function isManagerLevel(): bool
    {
        return in_array($this->role, [\App\Enums\AdminRole::Manager, \App\Enums\AdminRole::SubManager], true);
    }

    /**
     * Schools this account manages: a manager's own schools, or the
     * schools a manager assigned to this sub-manager.
     */
    public function institutions()
    {
        return $this->hasMany(Institution::class, $this->institutionOwnerColumn());
    }

    /**
     * Sub-managers created by this manager.
     */
    public function subManagers()
    {
        return $this->hasMany(self::class, 'manager_id')->where('role', \App\Enums\AdminRole::SubManager->value);
    }

    /**
     * The manager who created this sub-manager.
     */
    public function parentManager()
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    /**
     * Schools assigned to this sub-manager.
     */
    public function subManagerSchools()
    {
        return $this->hasMany(Institution::class, 'sub_manager_id');
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
        // A sub-sub-admin works on exactly the same schools as the
        // sub-admin who created them.
        if ($this->role === \App\Enums\AdminRole::SubSubAdmin) {
            $parent = $this->parentAdmin;

            return $parent ? $parent->assignedInstitutionIds() : [];
        }

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

    /**
     * The sub-admin who created this sub-sub-admin.
     */
    public function parentAdmin()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Sub-sub-admins created by this sub-admin.
     */
    public function subSubAdmins()
    {
        return $this->hasMany(self::class, 'parent_id')->where('role', \App\Enums\AdminRole::SubSubAdmin->value);
    }

    public function isSubSubAdmin(): bool
    {
        return $this->role === \App\Enums\AdminRole::SubSubAdmin;
    }

    /**
     * The account whose scope (country / assigned schools) applies to this
     * one: the parent sub-admin for a sub-sub-admin, otherwise the account itself.
     */
    public function scopeAdmin(): self
    {
        return $this->isSubSubAdmin() ? ($this->parentAdmin ?? $this) : $this;
    }

    /**
     * Permissions that actually apply right now. For a sub-sub-admin that is
     * what the sub-admin granted, limited to what that sub-admin still holds
     * (nothing at all if the sub-admin is gone or blocked).
     */
    public function effectivePermissions(): array
    {
        $own = is_array($this->permissions) ? $this->permissions : [];

        if (!$this->isSubSubAdmin()) {
            return $own;
        }

        $parent = $this->parentAdmin;
        if (!$parent || $parent->status !== 'active') {
            return [];
        }

        $parentPerms = is_array($parent->permissions) ? $parent->permissions : [];

        return array_values(array_intersect($own, $parentPerms));
    }

    public function students()
    {
        return $this->hasManyThrough(Student::class, Institution::class, $this->institutionOwnerColumn(), 'institution_id');
    }

    public function users()
    {
        return $this->hasManyThrough(User::class, Institution::class, $this->institutionOwnerColumn(), 'institution_id');
    }

    public function invoices()
    {
        return $this->hasMany(ManagerInvoice::class);
    }

}
