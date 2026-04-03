<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstitutionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'slogan'              => $this->slogan,
            'logo_url'            => $this->logo_url, 
            'academic_year'       => $this->academic_year,
            'examination_system'  => $this->examination_system,
            'physical_address'    => $this->physical_address,

            'email'               => $this->email,
            'alternate_email'     => $this->alternate_email,
            'phone_number'        => $this->phone_number,
            'alternate_phone'     => $this->alternate_phone,
            'telephone'           => $this->telephone,

            'email_verified'      => (bool) $this->email_verified,
            'phone_verified'      => (bool) $this->phone_verified,

            'category' => $this->whenLoaded('category', fn () => [
                'id'   => $this->category->id ?? null,
                'name' => $this->category->name ?? null,
            ]),
        ];
    }
}
