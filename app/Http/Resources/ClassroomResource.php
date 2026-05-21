<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassroomResource extends JsonResource
{
    public function toArray(Request $request): array
    {

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'institution_id' => $this->institution?->id,
            'institution_type' => $this->institution?->category->name,
            'total_subjects' => $this->relationLoaded('subjects') ? $this->subjects->count() : 0,
            'total_teachers' => $this->relationLoaded('subjects') ? $this->subjects->pluck('teacher_id')->filter()->unique()->count() : 0,
            'in_charge' => new UserResource($this->whenLoaded('inCharge')),
            'subjects' => SubjectResource::collection($this->whenLoaded('subjects')),
            'teachers' => UserResource::collection($this->whenLoaded('teachers')) ?? [],
            'students' => StudentResource::collection($this->whenLoaded('students')),
            'total_students' => $this->students_count ?? 0,
            'average_performance' => $this->average_performance ?? 0,
            'average_attendance' => $this->average_attendance ?? 0,
            'paid_tuition' => $this->paid_tuition ?? 0,
            'owing_tuition' => $this->owing_tuition ?? 0,
        ];
    }
}
