<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcademicDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'institution_id' => $this->institution_id,
            'student_id' => $this->student_id,
            'document_type' => $this->document_type,
            'title' => $this->title,
            'file_url' => $this->file_url,
            'student' => new StudentResource($this->whenLoaded('student')),
        ];

        if ($this->classroom_id !== null) {
            $data['classroom_id'] = $this->classroom_id;
        }

        return $data;
    }
}
