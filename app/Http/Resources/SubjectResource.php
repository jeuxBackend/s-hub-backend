<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id ?? "",
            'name'         => $this->name,
            'code'         => $this->code,
            // Safely load classroom only if it's available
            'classroom'    => $this->whenLoaded('classroom', function () {
                return new ClassroomResource($this->classroom);
            }),
            'created_at'   => $this->created_at?->toDateTimeString(),
        ];
    }
}
