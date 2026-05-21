<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $authId = auth()->id();

        return [
            'id'              => $this->id,
            'conversation_id' => $this->conversation_id,
            'body'            => $this->body,
            'attachment'      => $this->attachment_url,   // full public URL
            'attachment_type' => $this->attachment_type,
            'is_mine'         => $this->sender_id === $authId,
            'read_at'         => $this->read_at?->toISOString(),
            'sender'          => [
                'id'              => $this->sender->id,
                'full_name'       => $this->sender->full_name,
                'profile_picture' => $this->sender->profile_picture,
            ],
            'created_at'      => $this->created_at->toISOString(),
        ];
    }
}
