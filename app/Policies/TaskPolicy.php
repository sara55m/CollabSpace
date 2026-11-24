<?php

namespace App\Policies;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskPolicy
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
    public function view(User $user, Task $task): bool
    {
        //only task assignees or task reviewer or the project owner can view task
        return $task->assignees()->whereKey($user->id)->exists() ||
            $task->reviewer_id === $user->id ||
            $task->project->owner_id === $user->id;
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
    public function update(User $user, Task $task): bool
    {
        if ($task->status === TaskStatus::Done) {
            return false; // reopen first by changing status
        }

        $isOwner    = $task->project->owner_id === $user->id;
        $isReviewer = $task->reviewer_id === $user->id;

        // while in review, only the people reviewing can edit(reviewer||project owner)
        if ($task->status === TaskStatus::InReview) {
            return $isOwner || $isReviewer;
        }
        //only task assignees or task reviewer or the project owner can update task
        return $isOwner ||
            $isReviewer ||
            $task->assignees()->whereKey($user->id)->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Task $task): bool
    {
        //only project owner can delete task
        return $task->project->owner_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Task $task): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Task $task): bool
    {
        return false;
    }

    public function changeStatus(User $user,Task $task): bool
    {
        $isOwner=$task->project->owner_id===$user->id;
        $isReviewer=$task->reviewer_id===$user->id;
        $isAssignee=$task->assignees()->whereKey($user->id)->exists();

        return match ($task->status) {
            TaskStatus::Todo,
            TaskStatus::InProgress => $isAssignee || $isOwner,  // doing the work
            TaskStatus::InReview   => $isReviewer || $isOwner,   //approving or returning is allowed to reviewer (owner as a fallback) (in-review->done || in-review->in-progress)
            TaskStatus::Done       => $isReviewer || $isOwner,  // reopening a done task
        };
    }

    public function updateProgress(User $user, Task $task): bool
    {
        return $task->project->owner_id === $user->id
            || $task->assignees()->whereKey($user->id)->exists();
    }
}
