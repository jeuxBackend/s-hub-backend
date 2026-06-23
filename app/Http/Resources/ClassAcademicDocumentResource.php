<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassAcademicDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'institution_id' => $this->institution_id,
            'classroom_id' => $this->classroom_id,
            'document_type' => $this->document_type,
            'title' => $this->title,
            'file_url' => $this->file_url,
        ];
    }
}
