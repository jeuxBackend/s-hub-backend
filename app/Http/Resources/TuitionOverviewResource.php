<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TuitionOverviewResource extends JsonResource
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
            'institution' => $this->whenLoaded('institution', function () {
                return [
                    'id' => $this->institution->id,
                    'name' => $this->institution->name,
                ];
            }),
            'total_tuition' => (float) $this->total_tuition,
            'tuition_paid' => (float) $this->tuition_paid,
            'tuition_owing' => (float) $this->tuition_owing,
            'last_payment_date' => $this->last_payment_date,
        ];
    }
}
