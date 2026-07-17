<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $loadedRequirement = $this->whenLoaded('classSubjectRequirement');
        $requirement = $loadedRequirement instanceof \Illuminate\Http\Resources\MissingValue ? null : $loadedRequirement;
        $teacher = $requirement
            ? $requirement->teacher
            : null;

        return [
            'id'           => $this->id ?? "",
            'name'         => $this->name,
            'lectures_per_week' => (int) ($requirement?->lessons_per_week ?? 1),
            // 'code'         => $this->code,
            // Safely load classroom only if it's available
            'classroom'    => $this->whenLoaded('classroom', function () {
                return new ClassroomResource($this->classroom);
            }),
            'teacher'      => $teacher ? new UserResource($teacher) : null,
            'requirement'  => $requirement ? [
                'id' => $requirement->id,
                'teacher_id' => $requirement->teacher_id,
                'lessons_per_week' => (int) $requirement->lessons_per_week,
                'double_period_allowed' => (bool) $requirement->double_period_allowed,
                'priority' => (int) $requirement->priority,
                'is_active' => (bool) $requirement->is_active,
            ] : null,
            'start_time'   => null,
            'end_time'     => null,
            'created_at'   => $this->created_at?->toDateTimeString(),
        ];
    }
}
