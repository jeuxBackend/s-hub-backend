<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'sure_name' => $this->sure_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'role' => $this->role,
            'manager_id' => $this->manager_id,
            'parent_id' => $this->parent_id,
            'status' => $this->status,
            'region' => $this->region,
            'country' => $this->country,
            'state' => $this->state,
            'province' => $this->province,
            'city' => $this->city,
            // For a sub-sub-admin: what actually applies now (granted AND still held by its sub-admin).
            'permissions' => $this->isSubSubAdmin() ? $this->effectivePermissions() : $this->permissions,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'assigned_schools' => $this->whenLoaded('subManagerSchools', fn () => $this->subManagerSchools->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()),
            'profile_image' => $this->profile_image,
            'password_changed_at' => $this->password_changed_at?->toIso8601String(),
        ];
    }
}
