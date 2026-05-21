<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $auth = auth()->user();
        $authId = $auth->id;
        $isPrincipalOrAdmin = $auth->role === \App\Enums\UserRole::Principal || $auth->role === \App\Enums\UserRole::SchoolAdmin;
        
        $response = [
            'id'              => $this->id,
            'last_message'    => $this->whenLoaded('latestMessage', function () use ($authId) {
                $msg = $this->latestMessage;
                if (!$msg) return null;
                return [
                    'body'       => $msg->body,
                    'attachment_type' => $msg->attachment_type,
                    'is_mine'    => $msg->sender_id === $authId,
                    'created_at' => $msg->created_at?->toISOString(),
                ];
            }),
            'unread_count'    => $this->unreadCountFor($authId),
            'last_message_at' => $this->last_message_at?->toISOString(),
        ];

        if ($isPrincipalOrAdmin) {
            $response['user_one'] = [
                'id'              => $this->userOne->id,
                'full_name'       => $this->userOne->full_name,
                'role'            => $this->userOne->role?->value,
                'position'        => $this->userOne->position,
                'profile_picture' => $this->userOne->profile_picture,
            ];
            $response['user_two'] = [
                'id'              => $this->userTwo->id,
                'full_name'       => $this->userTwo->full_name,
                'role'            => $this->userTwo->role?->value,
                'position'        => $this->userTwo->position,
                'profile_picture' => $this->userTwo->profile_picture,
            ];
        } else {
            $participant = $this->participant($authId);
            $response['participant'] = [
                'id'              => $participant->id,
                'full_name'       => $participant->full_name,
                'role'            => $participant->role?->value,
                'position'        => $participant->position,
                'profile_picture' => $participant->profile_picture,
            ];
        }

        return $response;
    }
}
