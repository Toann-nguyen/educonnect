<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SchedulePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user)
    {
        return $user->hasRole(['admin', 'teacher'])
            || $user->hasPermission('view schedules');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Schedule $schedule)
    {
        return $user->hasRole(['admin', 'teacher'])
            || $user->hasPermission('view schedules')
            || $schedule->teacher_id === $user->id
            || $schedule->class_id === $user->student?->class_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user)
    {
        return $user->hasRole(['admin', 'teacher'])
            || $user->hasPermission('manage schedules');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Schedule $schedule)
    {
        return $user->hasRole('admin')
            || $user->hasPermission('manage schedules')
            || ($user->hasRole('teacher') && $schedule->teacher_id === $user->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Schedule $schedule)
    {
        return $user->hasRole('admin')
            || $user->hasPermission('manage schedules');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Schedule $schedule)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Schedule $schedule)
    {
        //
    }
}
