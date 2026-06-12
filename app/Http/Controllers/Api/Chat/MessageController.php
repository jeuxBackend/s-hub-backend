<?php

namespace App\Http\Controllers\Api\Chat;

use App\Events\MessageSent;
use App\Events\InboxUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\Chat\MessageResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MessageController extends Controller
{
    /**
     * POST /v1/chat/conversations/{conversation}/messages
     * Send a message (text and/or file attachment).
     */
    public function store(Request $request, Conversation $conversation)
    {
        $request->validate([
            'body'       => 'nullable|string|max:5000',
            'attachment' => 'nullable|file|max:20480', // 20 MB
        ]);

        // Require at least body or attachment
        if (empty($request->body) && !$request->hasFile('attachment')) {
            return $this->errorResponse('A message must have text or an attachment.', 422);
        }

        try {
            $auth = auth()->user();
            $authId = $auth->id;

            // Authorization: Principals cannot send messages
            if ($auth->role === \App\Enums\UserRole::Principal) {
                return $this->errorResponse('Principals cannot send messages.', 403);
            }

            // Authorization: only participants can send messages
            if ($conversation->user_one_id !== $authId && $conversation->user_two_id !== $authId) {
                abort(403, 'Unauthorized access to this conversation.');
            }

            // Handle file upload
            $attachmentPath = null;
            $attachmentType = null;

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $mime = $file->getMimeType();

                $attachmentType = str_starts_with($mime, 'image/') ? 'image' : 'file';
                $attachmentPath = $file->store('chat/attachments', 'public');
            }

            // Create the message
            $message = $conversation->messages()->create([
                'sender_id'       => $authId,
                'body'            => $request->body,
                'attachment'      => $attachmentPath,
                'attachment_type' => $attachmentType,
            ]);

            // Update conversation's last_message_at for inbox sorting
            $conversation->update(['last_message_at' => now()]);

            // Fire broadcast event — delivers to other participant via Reverb in real-time
            broadcast(new MessageSent($message))->toOthers();

            // Dispatch FCM notification to the receiver
            \App\Jobs\SendChatNotificationJob::dispatch($message);

            // Trigger global Inbox Update for the receiver
            $receiverId = $conversation->user_one_id === $authId ? $conversation->user_two_id : $conversation->user_one_id;
            $receiver = \App\Models\User::find($receiverId);
            
            if ($receiver) {
                $isPrincipal = $receiver->role === \App\Enums\UserRole::Principal;
                
                $inboxPayload = [
                    'id' => $conversation->id,
                    'last_message' => [
                        'body' => $message->body,
                        'attachment_type' => $message->attachment_type,
                        'is_mine' => false,
                        'created_at' => $message->created_at?->toISOString(),
                    ],
                    'unread_count' => $conversation->unreadCountFor($receiverId),
                    'last_message_at' => $conversation->last_message_at?->toISOString(),
                ];

                if ($isPrincipal) {
                    $conversation->load(['userOne', 'userTwo']);
                    $inboxPayload['user_one'] = [
                        'id' => $conversation->userOne->id,
                        'full_name' => $conversation->userOne->full_name,
                        'role' => $conversation->userOne->role?->value,
                        'position' => $conversation->userOne->position,
                        'profile_picture' => $conversation->userOne->profile_picture,
                    ];
                    $inboxPayload['user_two'] = [
                        'id' => $conversation->userTwo->id,
                        'full_name' => $conversation->userTwo->full_name,
                        'role' => $conversation->userTwo->role?->value,
                        'position' => $conversation->userTwo->position,
                        'profile_picture' => $conversation->userTwo->profile_picture,
                    ];
                } else {
                    $inboxPayload['participant'] = [
                        'id' => $auth->id,
                        'full_name' => $auth->full_name,
                        'role' => $auth->role?->value,
                        'position' => $auth->position,
                        'profile_picture' => $auth->profile_picture,
                    ];
                }

                broadcast(new \App\Events\InboxUpdatedEvent($inboxPayload, $receiverId))->toOthers();
            }
            
            // Additionally, notify principals if this is a teacher-parent conversation
            $this->notifyPrincipalsOfTeacherParentInteraction($conversation, $message);

            return $this->successResponse(
                new MessageResource($message->load('sender')),
                'Message sent successfully',
                201
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Notify principals when teachers and parents interact
     */
    private function notifyPrincipalsOfTeacherParentInteraction(Conversation $conversation, $message)
    {
        // Load the conversation participants if not already loaded
        $conversation->load(['userOne', 'userTwo']);
        
        $userOne = $conversation->userOne;
        $userTwo = $conversation->userTwo;
        
        // Check if this is a teacher-parent conversation
        $isTeacherParentConversation = false;
        if ($userOne && $userTwo) {
            $userOneIsTeacher = $userOne->role === \App\Enums\UserRole::Teacher || $userOne->role === \App\Enums\UserRole::SchoolAdmin;
            $userOneIsParent = $userOne->role === \App\Enums\UserRole::Parent;
            $userTwoIsTeacher = $userTwo->role === \App\Enums\UserRole::Teacher || $userTwo->role === \App\Enums\UserRole::SchoolAdmin;
            $userTwoIsParent = $userTwo->role === \App\Enums\UserRole::Parent;
            
            $isTeacherParentConversation = ($userOneIsTeacher && $userTwoIsParent) || 
                                          ($userOneIsParent && $userTwoIsTeacher);
        }
        
        if ($isTeacherParentConversation) {
            // Find all principals in the institutions of both users
            $institutionIds = [];
            if ($userOne && $userOne->institution_id) {
                $institutionIds[] = $userOne->institution_id;
            }
            if ($userTwo && $userTwo->institution_id) {
                $institutionIds[] = $userTwo->institution_id;
            }
            
            $institutionIds = array_unique($institutionIds);
            
            foreach ($institutionIds as $institutionId) {
                // Get the principal for this institution
                $principal = User::where('institution_id', $institutionId)
                    ->where('role', \App\Enums\UserRole::Principal)
                    ->first();
                    
                if ($principal) {
                    // Prepare payload for principal's inbox
                    $inboxPayload = [
                        'id' => $conversation->id,
                        'last_message' => [
                            'body' => $message->body,
                            'attachment_type' => $message->attachment_type,
                            'is_mine' => false, // From principal's perspective, this isn't their message
                            'created_at' => $message->created_at?->toISOString(),
                        ],
                        'unread_count' => $conversation->unreadCountFor($principal->id),
                        'last_message_at' => $conversation->last_message_at?->toISOString(),
                        'user_one' => [
                            'id' => $userOne->id,
                            'full_name' => $userOne->full_name,
                            'role' => $userOne->role?->value,
                            'position' => $userOne->position,
                            'profile_picture' => $userOne->profile_picture,
                        ],
                        'user_two' => [
                            'id' => $userTwo->id,
                            'full_name' => $userTwo->full_name,
                            'role' => $userTwo->role?->value,
                            'position' => $userTwo->position,
                            'profile_picture' => $userTwo->profile_picture,
                        ],
                    ];
                    
                    // Broadcast the event to the principal
                    broadcast(new \App\Events\InboxUpdatedEvent($inboxPayload, $principal->id))->toOthers();
                }
            }
        }
    }

    /**
     * PATCH /v1/chat/conversations/{conversation}/read
     * Mark all messages from the other participant as read.
     */
    public function markRead(Conversation $conversation)
    {
        try {
            $auth = auth()->user();
            $authId = $auth->id;

            // Authorization: Principals cannot mark messages as read
            if ($auth->role === \App\Enums\UserRole::Principal) {
                return $this->errorResponse('Principals cannot modify messages.', 403);
            }

            // Authorization: only participants can mark messages as read
            if ($conversation->user_one_id !== $authId && $conversation->user_two_id !== $authId) {
                abort(403, 'Unauthorized access to this conversation.');
            }

            $updated = $conversation->messages()
                ->where('sender_id', '!=', $authId)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return $this->successResponse(
                ['marked_read' => $updated],
                'Messages marked as read'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
