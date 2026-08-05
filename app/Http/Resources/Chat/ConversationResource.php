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
        $isPrincipalOrAdmin = $auth->role === \App\Enums\UserRole::Principal;
        
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
            $response['user_one'] = $this->whenLoaded('userOne')
                ? $this->formatParticipant($this->userOne)
                : null;
            
            $response['user_two'] = $this->whenLoaded('userTwo')
                ? $this->formatParticipant($this->userTwo)
                : null;
        } else {
            $participant = $this->participant($authId);
            $response['participant'] = $this->formatParticipant($participant);
        }

        return $response;
    }

    private function formatParticipant($participant): ?array
    {
        if (!$participant) {
            return null;
        }

        return [
            'id' => $participant->id,
            'full_name' => $participant->full_name,
            'role' => $participant->role?->value,
            'position' => $participant->position,
            'profile_picture' => $participant->profile_picture,
            'children' => $participant->role?->value === 'parent' && $participant->relationLoaded('guardianStudents')
                ? $participant->guardianStudents->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'first_name' => $child->first_name,
                        'last_name' => $child->last_name,
                        'sur_name' => $child->sur_name,
                        'classroom' => $child->classroom?->name,
                        'profile_picture' => $child->profile_picture,
                    ];
                })->values()
                : [],
        ];
    }
}
