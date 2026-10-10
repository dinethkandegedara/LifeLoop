<?php

namespace App\Policies;

use App\Models\RecurringSchedule;
use App\Models\User;

class RecurringSchedulePolicy
{
    /**
     * Determine whether the user can view the schedule.
     */
    public function view(User $user, RecurringSchedule $schedule): bool
    {
        return $user->id === $schedule->user_id;
    }

    /**
     * Determine whether the user can update the schedule.
     */
    public function update(User $user, RecurringSchedule $schedule): bool
    {
        return $user->id === $schedule->user_id;
    }

    /**
     * Determine whether the user can delete/deactivate the schedule.
     */
    public function delete(User $user, RecurringSchedule $schedule): bool
    {
        return $user->id === $schedule->user_id;
    }
}
