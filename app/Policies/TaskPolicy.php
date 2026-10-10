<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Determine whether the user can view any tasks.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the task.
     */
    public function view(User $user, Task $task): bool
    {
        return $task->user_id === $user->id;
    }

    /**
     * Determine whether the user can create tasks.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the task.
     */
    public function update(User $user, Task $task): bool
    {
        return $task->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the task.
     */
    public function delete(User $user, Task $task): bool
    {
        return $task->user_id === $user->id;
    }

    /**
     * Determine whether the user can archive the task.
     */
    public function archive(User $user, Task $task): bool
    {
        return $task->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore/unarchive the task.
     */
    public function unarchive(User $user, Task $task): bool
    {
        return $task->user_id === $user->id;
    }
}
