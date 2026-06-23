<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentAttendanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student' => StudentResource::make($this->whenLoaded('student')),
            'status' => $this->status?->value ?? null,
            'remarks' => $this->remarks,
            'reason' => $this->reason,
            'date' => $this->date?->toDateString() ?? "",
            'subject' => SubjectResource::make($this->whenLoaded('subject')),
            'term' => $this->term ?? null,
            'score' => $this->score ?? null,
            'type' => $this->type ?? null,
            'recorded_by' => UserResource::make($this->whenLoaded('recordedBy')),
        ];
    }
}
