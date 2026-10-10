<?php

namespace App\Policies;

use App\Models\ScheduleOccurrence;
use App\Models\User;

class ScheduleOccurrencePolicy
{
    /**
     * Determine whether the user can view the occurrence.
     */
    public function view(User $user, ScheduleOccurrence $occurrence): bool
    {
        return $user->id === $occurrence->user_id;
    }

    /**
     * Determine whether the user can update the occurrence.
     */
    public function update(User $user, ScheduleOccurrence $occurrence): bool
    {
        return $user->id === $occurrence->user_id;
    }

    /**
     * Determine whether the user can delete the occurrence.
     */
    public function delete(User $user, ScheduleOccurrence $occurrence): bool
    {
        return $user->id === $occurrence->user_id;
    }
}
