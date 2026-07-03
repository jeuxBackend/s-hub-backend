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
        return [
            'id'           => $this->id ?? "",
            'name'         => $this->name,
            'lectures_per_week' => (int) ($this->lectures_per_week ?? 1),
            // 'code'         => $this->code,
            // Safely load classroom only if it's available
            'classroom'    => $this->whenLoaded('classroom', function () {
                return new ClassroomResource($this->classroom);
            }),
            'teacher'      => new UserResource($this->whenLoaded('teacher')),
            'start_time'   => $this->start_time ? date('h:i a', strtotime($this->start_time)) : null,
            'end_time'     => $this->end_time ? date('h:i a', strtotime($this->end_time)) : null,
            'created_at'   => $this->created_at?->toDateTimeString(),
        ];
    }
}
