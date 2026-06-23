<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use App\Models\studentInvoices;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Subject;

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

    public function preregistrations()
    {
        return $this->hasMany(StudentPreregistration::class);
    }

    public function academicDocuments()
    {
        return $this->hasMany(AcademicDocument::class);
    }

    public function classroomSubjects()
    {
        return $this->belongsToMany(Subject::class, 'classroom_student_subject')
            ->withPivot(['classroom_id', 'term', 'academic_year'])
            ->withTimestamps();
    }

    /**
     * Define the assignments relationship (many-to-many).
     */
    public function assignments()
    {
        return $this->belongsToMany(Assignment::class, 'assignment_submissions')
            ->withPivot(['submitted_at', 'file_path', 'file_original_name', 'score', 'feedback'])
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

    /**
     * Define the assignmentSubmissions relationship (one-to-many).
     */
    public function assignmentSubmissions()
    {
        return $this->hasMany(AssignmentSubmission::class);
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

    public function promotionEligibility(): array
    {
        $grades = $this->relationLoaded('studentGrades')
            ? $this->studentGrades
            : $this->studentGrades()->get();

        $latestPromotion = $this->relationLoaded('preregistrations')
            ? $this->preregistrations->sortByDesc('id')->first()
            : $this->preregistrations()->latest('id')->first();

        $hasExamMarks = $grades->where('type', 'exam_marks')
            ->whereNotNull('score')
            ->isNotEmpty();

        $classroomSubjects = $this->relationLoaded('classroom') && $this->classroom
            ? $this->classroom->subjects
            : Subject::where('classroom_id', $this->classroom_id)->get();

        $yearMarksRecords = $grades->where('type', 'years_marks');
        $totalObtainedSum = 0;
        $totalMaxSum = 0;

        foreach ($classroomSubjects as $subject) {
            $latestGrade = $yearMarksRecords
                ->where('subject_id', $subject->id)
                ->sortByDesc('id')
                ->first();

            $obtained = $latestGrade ? (float) $latestGrade->score : 0;
            $max = $latestGrade ? (float) $latestGrade->total : 0;

            $totalObtainedSum += $obtained;
            $totalMaxSum += $max;
        }

        $overallPercentage = $totalMaxSum > 0
            ? round(($totalObtainedSum / $totalMaxSum) * 100, 2)
            : 0;

        $hasYearMarks = $totalMaxSum > 0;
        $promotionStatus = $latestPromotion?->status;
        $promotionSent = in_array($promotionStatus, ['submitted', 'approved'], true);
        $eligible = $hasExamMarks && $hasYearMarks && $overallPercentage > 50 && !$promotionSent;

        if (!$hasExamMarks) {
            $reason = 'No exam marks available yet.';
        } elseif (!$hasYearMarks) {
            $reason = 'Year marks are not calculated yet.';
        } elseif ($overallPercentage <= 50) {
            $reason = 'Year marks must be above 50% to promote.';
        } elseif ($promotionSent) {
            $reason = 'Promotion request already sent.';
        } else {
            $reason = 'Eligible for promotion.';
        }

        return [
            'has_exam_marks' => $hasExamMarks,
            'overall_percentage' => $overallPercentage,
            'eligible' => $eligible,
            'promotion_sent' => $promotionSent,
            'promotion_status' => $promotionStatus,
            'promotion_id' => $latestPromotion?->id,
            'reason' => $reason,
        ];
    }
}
