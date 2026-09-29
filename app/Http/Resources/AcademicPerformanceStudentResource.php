<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcademicPerformanceStudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'sur_name' => $this->sur_name,
            'profile_picture' => $this->profile_picture,
            'registration_number' => $this->registration_number,
            'address' => $this->address,
            'institution' => $this->whenLoaded('institution', fn () => [
                'id' => $this->institution->id,
                'name' => $this->institution->name,
                'region' => $this->institution->region,
            ]),
            'classroom' => $this->whenLoaded('classroom', fn () => [
                'id' => $this->classroom->id,
                'name' => $this->classroom->name,
            ]),
            'guardian_contact' => $this->guardian_contact,
            'subjects' => $this->subjects,
            'obtained_marks' => $this->obtained_marks,
            'total_marks' => $this->total_marks,
            'percentage' => $this->percentage,
        ];
    }
}
