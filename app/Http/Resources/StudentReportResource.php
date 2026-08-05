<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student' => [
                'id' => $this->student->id ?? null,
                'first_name' => $this->student->first_name ?? null,
                'last_name' => $this->student->last_name ?? null,
                'sur_name' => $this->student->sur_name ?? null,
            ],
            'classroom' => [
                'id' => $this->classroom->id ?? null,
                'name' => $this->classroom->name ?? null,
            ],
            'teacher' => [
                'id' => $this->teacher->id ?? null,
                'name' => $this->teacher->full_name ?? null,
            ],
            'report_type' => $this->report_type,
            'rejection_reason' => $this->reason,
            'title' => $this->title,
            'description' => $this->description,
            'file_url' => $this->file_url,
            'status' => $this->status,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
