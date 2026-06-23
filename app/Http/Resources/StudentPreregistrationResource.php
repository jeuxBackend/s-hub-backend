<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentPreregistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'academic_year' => $this->academic_year,
            'submitted_at' => $this->submitted_at?->toDateTimeString(),
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'notes' => $this->notes,
            'student' => new StudentResource($this->whenLoaded('student')),
            'guardian' => new UserResource($this->whenLoaded('guardian')),
            'current_classroom' => new ClassroomResource($this->whenLoaded('currentClassroom')),
            'target_classroom' => new ClassroomResource($this->whenLoaded('targetClassroom')),
        ];
    }
}
