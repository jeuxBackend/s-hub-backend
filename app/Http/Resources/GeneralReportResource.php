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
            'reporter_type' => $this->reporter_type === \App\Models\Admin::class ? 'admin' : 'user',
            'reporter' => [
                'id' => $this->reporter->id ?? null,
                'first_name' => $this->reporter->first_name ?? null,
                'last_name' => $this->reporter->last_name ?? null,
                'sur_name' => $this->reporter->sur_name ?? $this->reporter->sure_name ?? null,
                'full_name' => $this->reporter->full_name ?? $this->reporter->name ?? null,
                'role' => $this->reporter->role?->value ?? null,
                'profile_picture' => $this->reporter->profile_picture ?? null,
                'email' => $this->reporter->email ?? null,
            ],
            'reported_to_role' => $this->reported_to_role,
            'institution_id' => $this->institution_id,
            'institution' => $this->whenLoaded('institution', function () {
                return [
                    'id' => $this->institution->id,
                    'name' => $this->institution->name,
                ];
            }),
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'response' => $this->response,
            'read_at' => $this->read_at?->toIso8601String(),
            'is_read' => $this->read_at !== null,
            'resolved_by' => $this->whenLoaded('resolvedBy', function () {
                return [
                    'id' => $this->resolvedBy->id,
                    'first_name' => $this->resolvedBy->first_name,
                    'last_name' => $this->resolvedBy->last_name,
                    'sur_name' => $this->resolvedBy->sur_name ?? $this->resolvedBy->sure_name ?? null,
                    'full_name' => $this->resolvedBy->full_name ?? $this->resolvedBy->name ?? null,
                    'role' => $this->resolvedBy->role?->value,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
