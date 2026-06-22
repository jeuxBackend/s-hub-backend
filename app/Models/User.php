<?php

namespace App\Models;

use App\Enums\{UserRole, GuardianType};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    // Mass assignable fields
    protected $fillable = [
        'email',
        'phone_number',
        'password',
        'role',
        'otp_code',
        'otp_verified',
        'device_id',
        'fcm_token',
        'permissions',

        'first_name',
        'sur_name',
        'title',
        'position',
        'country',
        'profile_picture',
        'staff_number',

        'address',
        'latitude',
        'longitude',

        'security_question',
        'answer_security_question',

        'guardian_type',
        'guardian_name',
        'guardian_relation',
        'guardian_phone_number',
        'alternative_guardian_phone_number',

        'alternative_email',
        'alternative_phone_number',


        'institution_id',
        'principal_id',

        'created_by',

        'status',
        'notifications_enabled',
        'email_verified_at',
        'remote_teaching',
        'timezone',
    ];

    // Hidden attributes
    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
        'answer_security_question',
        'fcm_token',
        'device_id'
    ];

    // Attribute casting
    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'remote_teaching' => 'boolean',
            'otp_verified' => 'boolean',
            'notifications_enabled' => 'boolean',
            'email_verified_at' => 'datetime',
            'otp_code' => 'hashed',
            'password' => 'hashed',
            'permissions' => 'json',
            'role' => UserRole::class,
            'guardian_type' => GuardianType::class,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */
    public function getProfilePictureAttribute($value): string
    {
        if (!$value) {
            return asset('defaults/user.png');
        }

        // If already contains folder path
        if (str_contains($value, '/')) {
            return asset('storage/' . $value);
        }

        // Default folder
        return asset('candidatefiles/' . $value);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->sur_name}");
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdUsers()
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function principal()
    {
        return $this->belongsTo(Institution::class, 'principal_id');
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class, 'institution_id');
    }

    public function classrooms()
    {
        return $this->belongsToMany(Classroom::class, 'classroom_teachers', 'teacher_id', 'classroom_id')
            ->using(ClassroomTeacher::class)
            ->withPivot('assigned_by', 'term', 'year', 'section')
            ->withTimestamps();
    }

    public function guardianStudents()
    {
        return $this->hasMany(Student::class, 'guardian_id');
    }

    /**
     * Relationship for assignments created by the teacher.
     */
    public function assignments()
    {
        return $this->hasMany(Assignment::class, 'teacher_id');
    }

    public function publishedFinalResults()
    {
        return $this->hasMany(FinalResultSubmission::class, 'published_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Role / Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    public function isBlocked(): bool
    {
        return !$this->status;
    }
}
