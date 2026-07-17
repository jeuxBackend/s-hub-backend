<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'institution_id' => $this->institution_id,
            'classroom_id' => $this->classroom_id,
            'subject_id' => $this->subject_id,
            'teacher_id' => $this->teacher_id,
            'document_type' => $this->document_type,
            'title' => $this->title,
            'description' => $this->description,
            'file_original_name' => $this->file_original_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'academic_year' => $this->academic_year,
            'term' => $this->term,
            'file_url' => $this->file_url,
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
            'classroom' => $this->whenLoaded('classroom', fn () => [
                'id' => $this->classroom?->id,
                'name' => $this->classroom?->name,
                'code' => $this->classroom?->code,
            ]),
            'subject' => $this->whenLoaded('subject', fn () => [
                'id' => $this->subject?->id,
                'name' => $this->subject?->name,
                'code' => $this->subject?->code,
            ]),
            'teacher' => $this->whenLoaded('teacher', fn () => [
                'id' => $this->teacher?->id,
                'full_name' => $this->teacher?->full_name,
            ]),
        ];
    }
}
