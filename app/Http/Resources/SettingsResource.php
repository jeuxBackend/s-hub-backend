<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
          return [
            'id'                => $this->id,
            'institution_id'    => $this->institution_id,
            'about_us'          => $this->about_us,
            'privacy_policy'    => $this->privacy_policy,
            'terms_conditions'  => $this->terms_conditions,
            'created_by'        => $this->createdBy?->full_name, // optional, assuming relation exists
            'created_at'        => $this->created_at?->toDateTimeString(),
            'updated_at'        => $this->updated_at?->toDateTimeString(),
        ];
    }
}
