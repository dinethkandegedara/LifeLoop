<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
        'calendar_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the OTPs associated with the user.
     */
    public function emailOtps(): HasMany
    {
        return $this->hasMany(EmailOtp::class);
    }

    /**
     * Get the tasks created by the user.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the recurring schedules created by the user.
     */
    public function recurringSchedules(): HasMany
    {
        return $this->hasMany(RecurringSchedule::class);
    }

    /**
     * Get the schedule occurrences for the user.
     */
    public function scheduleOccurrences(): HasMany
    {
        return $this->hasMany(ScheduleOccurrence::class);
    }

    /**
     * Get the work sessions recorded by the user.
     */
    public function workSessions(): HasMany
    {
        return $this->hasMany(WorkSession::class);
    }

    /**
     * Get or generate a secure calendar feed token for the user.
     */
    public function getCalendarToken(): string
    {
        if (empty($this->calendar_token)) {
            $this->calendar_token = bin2hex(random_bytes(32));
            $this->saveQuietly();
        }

        return $this->calendar_token;
    }

    /**
     * Regenerate the calendar feed token.
     */
    public function regenerateCalendarToken(): string
    {
        $this->calendar_token = bin2hex(random_bytes(32));
        $this->saveQuietly();

        return $this->calendar_token;
    }
}
