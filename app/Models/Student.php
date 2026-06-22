<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use App\Models\studentInvoices;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile_picture',
        'first_name',
        'sur_name',
        'student_phone_number',
        'gender',
        'dob',
        'age',
        'religion',
        'term',
        'classroom_id',
        'institution_id',
        'guardian_id',
        'created_by',
        'registration_number',
        'status',
        'address',
        'is_flag',
    ];

    protected $casts = [
        'status' => 'boolean',
        'gender' => \App\Enums\GenderType::class,
        'dob' => 'date',
        'term' => \App\Enums\TermType::class,
        'is_flag' => 'boolean',
    ];

    // ✅ Accessor
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

    // 👥 Relationships
    public function guardian()
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function feeRecords()
    {
        return $this->hasMany(StudentFee::class);
    }

    public function classroomSubjects()
    {
        return $this->belongsToMany(Subject::class, 'classroom_student_subject')
            ->withPivot(['classroom_id', 'term', 'academic_year'])
            ->withTimestamps();
    }

    public function attendanceRecords()
    {
        return $this->hasMany(StudentAttendance::class);
    }

    public function studentInvoices()
    {
        return $this->hasMany(StudentInvoice::class);
    }

    public function studentGrades()
    {
        return $this->hasMany(StudentGrade::class);
    }

    public function finalResultSubmissions()
    {
        return $this->hasMany(FinalResultSubmission::class, 'classroom_id', 'classroom_id');
    }

    // ✅ Today's attendance relation
    public function todayAttendance()
    {
        return $this->hasOne(StudentAttendance::class)->whereDate('date', today());
    }

    // ✅ Optional: Generic attendance on a given date (not eager-loadable)
    public function attendanceOn($date)
    {
        return $this->hasOne(StudentAttendance::class)->whereDate('date', $date);
    }

    // Relationship to classroom
    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function toggleStatus()
    {
        return !$this->status;
    }
}
