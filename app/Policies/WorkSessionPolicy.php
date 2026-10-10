<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkSession;

class WorkSessionPolicy
{
    /**
     * Determine whether the user can view the work session.
     */
    public function view(User $user, WorkSession $workSession): bool
    {
        return $user->id === $workSession->user_id;
    }

    /**
     * Determine whether the user can update the work session.
     */
    public function update(User $user, WorkSession $workSession): bool
    {
        return $user->id === $workSession->user_id;
    }

    /**
     * Determine whether the user can delete the work session.
     */
    public function delete(User $user, WorkSession $workSession): bool
    {
        return $user->id === $workSession->user_id;
    }
}
