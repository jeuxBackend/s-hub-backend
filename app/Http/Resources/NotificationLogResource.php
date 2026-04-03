<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationLogResource extends JsonResource
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
            'id'               => $this->id,
            'user_id'          => $this->user_id,
            'user_name'        => $this->user?->full_name,
            'title'            => $this->title,
            'body'             => $this->body,
            'type'             => $this->type,
            'meta'            => $this->meta,
            'created_at'       => $this->created_at->toDateTimeString(),
        ];
    }
}
