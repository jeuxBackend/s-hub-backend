<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'first_name'              => $this->first_name,
            'sur_name'                => $this->sur_name,
            'full_name'               => trim($this->first_name . ' ' . $this->sur_name),
            'profile_picture_url'     => $this->profile_picture_url,
            'student_phone_number'    => $this->student_phone_number,
            'gender'                  => $this->gender->value ?? null,
            'age'                     => $this->age,
            'religion'                => $this->religion,
            'term'                    => $this->term->value ?? null,
            'registration_number'     => $this->registration_number,
            'status'                  => $this->status,
            'address'                 => $this->address,
            'guardian'                => new UserResource($this->whenLoaded('guardian')),
            'classroom'               => new ClassroomResource($this->whenLoaded('classroom')),
            'institution_id'          => $this->institution_id,
            'created_by'              => $this->created_by,
            'created_by_name'         => $this->createdBy?->full_name,
            'classroom_subjects'      => SubjectResource::collection($this->whenLoaded('classroomSubjects')),
            'fees'                    => StudentFeeResource::collection($this->whenLoaded('feeRecords')),
            'attendance_records'      => StudentAttendanceResource::collection($this->whenLoaded('attendanceRecords')),
            'teachers'                => UserResource::collection($this->classroom?->teachers ?? []),
            'today_attendance'        => new StudentAttendanceResource($this->whenLoaded('todayAttendance')),
        ];
    }
}
