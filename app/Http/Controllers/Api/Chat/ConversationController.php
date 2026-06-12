<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Http\Resources\Chat\ConversationResource;
use App\Http\Resources\Chat\MessageResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Throwable;

class ConversationController extends Controller
{
    /**
     * GET /v1/chat/conversations
     * Inbox — list all conversations for the auth user, ordered by most recent message.
     */
    public function index()
    {
        try {
            $auth = auth()->user();
            $authId = $auth->id;

            $query = Conversation::query();

            if ($auth->role === \App\Enums\UserRole::Principal) {
                $institutionId = $auth->institution_id;
                $query->where(function ($q) use ($institutionId) {
                    $q->whereHas('userOne', function ($q2) use ($institutionId) {
                        $q2->where('institution_id', $institutionId);
                    })->orWhereHas('userTwo', function ($q2) use ($institutionId) {
                        $q2->where('institution_id', $institutionId);
                    });
                });
                
                // Additional filter: Only show conversations where both user1 and user2 exist
                $query->whereHas('userOne')->whereHas('userTwo');
            } else {
                $query->where(function ($q) use ($authId) {
                    $q->where('user_one_id', $authId)
                        ->orWhere('user_two_id', $authId);
                });
            }

            $conversations = $query->with(['userOne', 'userTwo', 'latestMessage.sender'])
                ->orderByDesc('last_message_at')
                ->get();

            return $this->successResponse(
                ConversationResource::collection($conversations),
                'Conversations retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * POST /v1/chat/conversations
     * Start a new conversation or return the existing one.
     * Body: { "recipient_id": 12 }
     */
    public function store(Request $request)
    {
        $request->validate([
            'recipient_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $auth = auth()->user();
            $authId = $auth->id;

            if ($auth->role === \App\Enums\UserRole::Principal) {
                return $this->errorResponse('Principals cannot start conversations.', 403);
            }

            $recipientId = (int) $request->recipient_id;

            if ($authId === $recipientId) {
                return $this->errorResponse('You cannot start a conversation with yourself.', 422);
            }

            $conversation = Conversation::findOrCreateBetween($authId, $recipientId);
            $conversation->load(['userOne', 'userTwo', 'latestMessage.sender']);

            return $this->successResponse(
                new ConversationResource($conversation),
                'Conversation ready'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * GET /v1/chat/conversations/{conversation}
     * Open a conversation — returns paginated messages (newest first).
     * Also marks all unread messages from the other person as read.
     */
    public function show(Conversation $conversation)
    {
        try {
            $auth = auth()->user();
            $authId = $auth->id;
            $isPrincipalOrAdmin = $auth->role === \App\Enums\UserRole::Principal;

            // Authorization: only participants or institution admin can open the chat
            if (!$isPrincipalOrAdmin && $conversation->user_one_id !== $authId && $conversation->user_two_id !== $authId) {
                abort(403, 'Unauthorized access to this conversation.');
            }

            if ($isPrincipalOrAdmin) {
                $institutionId = $auth->institution_id;
                // Principal can only view conversations if one of the users belongs to their institution
                $userOneInstitutionId = $conversation->userOne?->institution_id;
                $userTwoInstitutionId = $conversation->userTwo?->institution_id;
                
                if (($userOneInstitutionId !== $institutionId && $userTwoInstitutionId !== $institutionId) 
                    || (!$userOneInstitutionId && !$userTwoInstitutionId)) {
                    abort(403, 'Unauthorized access to this conversation.');
                }
                
                // Additionally, ensure both userOne and userTwo exist for principal to access
                if (!$conversation->userOne || !$conversation->userTwo) {
                    abort(403, 'Unauthorized access to this conversation.');
                }
            }

            // Mark messages from the OTHER participant as read, only if participant
            if (!$isPrincipalOrAdmin && ($conversation->user_one_id === $authId || $conversation->user_two_id === $authId)) {
                $conversation->messages()
                    ->where('sender_id', '!=', $authId)
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
            }

            // Paginate messages — newest first so client can implement reverse scroll
            $messages = $conversation->messages()
                ->with('sender')
                ->orderByDesc('created_at')
                ->paginate(20);

            return $this->successResponse([
                'conversation' => new ConversationResource(
                    $conversation->load(['userOne', 'userTwo', 'latestMessage'])
                ),
                'messages' => MessageResource::collection($messages),
                'pagination' => [
                    'total' => $messages->total(),
                    'per_page' => $messages->perPage(),
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                ],
            ], 'Messages retrieved successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}