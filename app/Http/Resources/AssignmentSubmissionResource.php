<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentSubmissionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'assignment' => new AssignmentResource($this->whenLoaded('assignment')),
            'student' => new StudentResource($this->whenLoaded('student')),
            'submitted_at' => $this->submitted_at ? $this->submitted_at->toISOString() : null,
            'file_path' => $this->file_path,
            'file_original_name' => $this->file_original_name,
            'score' => $this->score,
            'feedback' => $this->feedback,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}