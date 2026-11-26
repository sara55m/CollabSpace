<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class MessagePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Message $message): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Message $message): bool
    {
        //only message sender can update the message
        return $message->user_id === $user->id
        && app(ConversationPolicy::class)->sendMessage($user, $message->conversation);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Message $message): Response
    {
        if(! $this->update($user,$message)){
            return Response::deny(__('messages.cannot_delete_message'));
        }

        if (! $message->withinDeleteWindow()) {
            return Response::deny(__('messages.delete_window_expired', [
                'minutes' => config('conversation.delete_window_minutes'),
            ]));
        }

        return Response::allow();

    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Message $message): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Message $message): bool
    {
        return false;
    }
}
