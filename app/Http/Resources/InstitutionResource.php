<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstitutionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slogan' => $this->slogan,
            'logo' => $this->logo,
            'academic_year' => $this->academic_year,
            'examination_system' => $this->examination_system,
            'physical_address' => $this->physical_address,

            'email' => $this->email,
            'alternate_email' => $this->alternate_email,
            'phone_number' => $this->phone_number,
            'alternate_phone' => $this->alternate_phone,
            'telephone' => $this->telephone,

            'email_verified' => (bool) $this->email_verified,
            'phone_verified' => (bool) $this->phone_verified,
            'alert_feature_enabled' => (bool) $this->alert_feature_enabled,
            'allowed_alert_types' => $this->allowed_alert_types ?? [],
            'mock_exam_classroom_ids' => $this->mock_exam_classroom_ids ?? [],
            'active_alerts_count' => $this->when(isset($this->active_alerts_count), (int) $this->active_alerts_count),
            'active_alerts_created_by' => $this->when(isset($this->active_alerts_created_by), $this->active_alerts_created_by),
            'potential_abduction_alerts_count' => $this->when(isset($this->potential_abduction_alerts_count), (int) $this->potential_abduction_alerts_count),
            'potential_abduction_alerts_created_by' => $this->when(isset($this->potential_abduction_alerts_created_by), $this->potential_abduction_alerts_created_by),
            'students_count' => $this->when(isset($this->students_count), (int) $this->students_count),
            'teachers_count' => $this->when(isset($this->teachers_count), (int) $this->teachers_count),
            'parents_count' => $this->when(isset($this->parents_count), (int) $this->parents_count),
            'school_admins_count' => $this->when(isset($this->school_admins_count), (int) $this->school_admins_count),
            'classrooms_count' => $this->when(isset($this->classrooms_count), (int) $this->classrooms_count),

            'category' => $this->whenLoaded('category', fn() => [
                'id' => $this->category->id ?? null,
                'name' => $this->category->name ?? null,
            ]),
        ];
    }
}
