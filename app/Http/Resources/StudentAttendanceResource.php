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
            'id'         => $this->id,
            'student'    => StudentResource::make($this->whenLoaded('student'))->additional(['without' => ['teachers']]),
            'subject'    => SubjectResource::make($this->whenLoaded('subject')),
            'status'     => $this->status,
            'remarks'    => $this->remarks,
            'marked_by'  => UserResource::make($this->whenLoaded('marked_by')),
            'date'       => $this->date?->toDateString(),
        ];
    }
}
