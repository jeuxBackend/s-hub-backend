<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title, // Assignment title
            'assignment_text' => $this->assignment_text, // assignment_text
            'classroom' => new ClassroomResource($this->whenLoaded('classroom')),
            'subject' => new SubjectResource($this->whenLoaded('subject')),
            'teacher' => new UserResource($this->whenLoaded('teacher')),
            'status' => $this->status,
            'file_path' => $this->file_path,
            'file_original_name' => $this->file_original_name,
            'submission_end_date' => $this->submission_end_date ? $this->submission_end_date->toISOString() : null, // submission_end_date
            'assignment_date' => $this->assignment_date ? $this->assignment_date->toISOString() : null, // assignment_date
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}