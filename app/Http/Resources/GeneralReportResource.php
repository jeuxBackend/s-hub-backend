<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneralReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reporter' => [
                'id' => $this->reporter->id ?? null,
                'first_name' => $this->reporter->first_name ?? null,
                'last_name' => $this->reporter->last_name ?? null,
                'sur_name' => $this->reporter->sur_name ?? $this->reporter->sure_name ?? null,
                'full_name' => $this->reporter->full_name ?? null,
                'role' => $this->reporter->role?->value ?? null,
                'profile_picture' => $this->reporter->profile_picture ?? null,
                'email' => $this->reporter->email ?? null,
            ],
            'reported_to_role' => $this->reported_to_role,
            'institution_id' => $this->institution_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'response' => $this->response,
            'resolved_by' => $this->whenLoaded('resolvedBy', function () {
                return [
                    'id' => $this->resolvedBy->id,
                    'first_name' => $this->resolvedBy->first_name,
                    'last_name' => $this->resolvedBy->last_name,
                    'sur_name' => $this->resolvedBy->sur_name ?? $this->resolvedBy->sure_name ?? null,
                    'full_name' => $this->resolvedBy->full_name,
                    'role' => $this->resolvedBy->role?->value,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
