<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthorizedPickupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'sur_name' => $this->sur_name,
            'relationship' => $this->relationship,
            'phone_number' => $this->phone_number,
            'address' => $this->address,
            'is_current' => $this->when(
                $request->user()?->id === $this->parent_id,
                fn () => $this->id === $request->user()->current_authorized_pickup_id
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
