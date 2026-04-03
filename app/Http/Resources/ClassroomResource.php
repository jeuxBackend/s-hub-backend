<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassroomResource extends JsonResource
{
    public function toArray(Request $request): array
    {

        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'code'           => $this->code,
            'institution_id' => $this->institution?->id, // ✅ safe now
            'subjects'       => SubjectResource::collection($this->whenLoaded('subjects')),
            'teachers'       => UserResource::collection($this->whenLoaded('teachers')) ?? [],
            'students' => StudentResource::collection($this->whenLoaded('students')), // ✅ include this
        ];
    }
}
