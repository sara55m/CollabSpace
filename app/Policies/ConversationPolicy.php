<?php

namespace App\Policies;

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;


class ConversationPolicy
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
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->participants()->whereKey($user->id)->exists();
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
    public function update(User $user, Conversation $conversation): Response
    {
        //allow editing team project conversations only(no public, no direct)
        if($conversation->type !== ConversationType::Project){
            return Response::deny();
        };

        if ($conversation->is_public) {
            return Response::deny(__('messages.public_channel_locked'));
        }

        return $this->sendMessage($user, $conversation)
            ? Response::allow()
            : Response::deny();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Conversation $conversation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Conversation $conversation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Conversation $conversation): bool
    {
        return false;
    }

    public function sendMessage(User $user,Conversation $conversation): bool
    {
        //message sender must be a member of the conversation
        $isParticipant=$conversation->participants()->whereKey($user->id)->exists();

        if (! $isParticipant) {
            return false;
        }

        //Group conversation :message sender must still be a member of the project
        if($conversation->type === ConversationType::Project){
            return DB::table('project_user')
            ->where('project_id',$conversation->project->id)
            ->where('user_id',$user->id)
            ->exists();
        }

        //Direct conversation:the 2 users must have at least one shared project
        $otherId = $conversation->participants()
        ->where('users.id', '!=', $user->id)
        ->value('users.id');

        return $otherId !== null && DB::table('project_user as mine')
            ->join('project_user as theirs', 'theirs.project_id', '=', 'mine.project_id')
            ->where('mine.user_id', $user->id)
            ->where('theirs.user_id', $otherId)
            ->exists();
    }
}
