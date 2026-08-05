<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $yearMarks = $this->relationLoaded('studentGrades') ? $this->studentGrades->where('type', 'years_marks') : collect();
        $perfScore = $yearMarks->sum('score');
        $perfTotal = $yearMarks->sum('total');
        if ($perfTotal > 0) {
            $performancePercentage = round(($perfScore / $perfTotal) * 100, 2);
            if ($performancePercentage > 70) {
                $performanceIndicator = 'good';
            } elseif ($performancePercentage > 50) {
                $performanceIndicator = 'fair';
            } else {
                $performanceIndicator = 'poor';
            }
        } else {
            $performancePercentage = 0;
            $performanceIndicator = 'N/A';
        }

        $latestInvoice = $this->relationLoaded('studentInvoices') ? $this->studentInvoices->sortByDesc('id')->first() : null;
        $promotionEligibility = method_exists($this->resource, 'promotionEligibility')
            ? $this->resource->promotionEligibility()
            : [
                'has_exam_marks' => false,
                'overall_percentage' => 0,
                'eligible' => false,
                'promotion_sent' => false,
                'promotion_status' => null,
                'promotion_id' => null,
                'reason' => 'Promotion eligibility unavailable.',
            ];

        $attendancePercentage = 0;
        $attendanceIndicator = 'N/A';
        if ($this->relationLoaded('attendanceRecords')) {
            $totalDays = $this->attendanceRecords->count();
            if ($totalDays > 0) {
                $presentDays = $this->attendanceRecords->filter(function ($record) {
                    $val = $record->status instanceof \UnitEnum ? $record->status->value : $record->status;
                    return in_array($val, ['present', 'late']);
                })->count();
                $attendancePercentage = round(($presentDays / $totalDays) * 100, 2);
                
                if ($attendancePercentage > 70) {
                    $attendanceIndicator = 'good';
                } elseif ($attendancePercentage > 50) {
                    $attendanceIndicator = 'fair';
                } else {
                    $attendanceIndicator = 'poor';
                }
            }
        }

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'sur_name' => $this->sur_name,
            'profile_picture' => $this->profile_picture,
            'student_phone_number' => $this->student_phone_number,
            'gender' => $this->gender->value ?? null,
            'dob' => $this->dob?->toDateString(),
            'age' => $this->age,
            'religion' => $this->religion,
            'term' => $this->term->value ?? null,
            'registration_number' => $this->registration_number,
            'status' => $this->status,
            'attendance_status' => $this->attendance_status ?? null,
            'address' => $this->address,
            'parent' => new UserResource($this->whenLoaded('guardian')),
            'authorized_pickup' => $this->when(
                $this->relationLoaded('guardian'),
                function () {
                    if (!$this->guardian || !$this->guardian->relationLoaded('authorizedPickup')) {
                        return null;
                    }

                    return $this->guardian->authorizedPickup
                        ? new AuthorizedPickupResource($this->guardian->authorizedPickup)
                        : null;
                }
            ),
            'classroom' => new ClassroomResource($this->whenLoaded('classroom')),
            'institution_id' => $this->institution_id,
            'created_by' => $this->created_by,
            'created_by_name' => $this->createdBy?->full_name,
            'classroom_subjects' => SubjectResource::collection($this->whenLoaded('classroomSubjects')),
            'fees' => StudentFeeResource::collection($this->whenLoaded('feeRecords')),
            'attendance_records' => StudentAttendanceResource::collection($this->whenLoaded('attendanceRecords')),
            'teachers' => UserResource::collection($this->classroom?->teachers ?? []),
            'today_attendance' => new StudentAttendanceResource($this->whenLoaded('todayAttendance')),
            'total_paid' => $this->relationLoaded('studentInvoices') ? $this->studentInvoices->sum('paid_amount') : 0,
            'total_due' => $latestInvoice ? $latestInvoice->due_amount : 0,
            'tuition_status' => $latestInvoice?->status,
            'performance_percentage' => $performancePercentage,
            'performance_indicator' => $performanceIndicator,
            'attendance_percentage' => $attendancePercentage,
            'attendance_indicator' => $attendanceIndicator,
            'can_promote' => $promotionEligibility['eligible'],
            'promotion_sent' => $promotionEligibility['promotion_sent'],
            'promotion_status' => $promotionEligibility['promotion_status'],
            'promotion_id' => $promotionEligibility['promotion_id'],
        ];
    }
}
