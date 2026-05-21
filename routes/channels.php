<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

/**
 * Private chat channel authorization.
 *
 * Channel: private-chat.{conversationId}
 *
 * Only the TWO participants of the conversation may subscribe.
 * Anyone else will receive a 403 Forbidden from Reverb.
 */
Broadcast::channel('chat.{conversationId}', function (User $user, int $conversationId) {
    $conversation = Conversation::find($conversationId);

    if (!$conversation) {
        return false;
    }

    if ($conversation->user_one_id === $user->id || $conversation->user_two_id === $user->id) {
        return true;
    }

    if ($user->role === \App\Enums\UserRole::Principal || $user->role === \App\Enums\UserRole::SchoolAdmin) {
        $conversation->load(['userOne', 'userTwo']);
        if ($conversation->userOne->institution_id === $user->institution_id || $conversation->userTwo->institution_id === $user->institution_id) {
            return true;
        }
    }

    return false;
});
