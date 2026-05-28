<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    protected $fillable = [
        'group_id',
        'location_id',
        'coach_id',
        'initial_coach_id',
        'lesson_date',
        'day_of_week',
        'start_time',
        'end_time',
        'lesson_type',
        'cancelled_at',
        'cancellation_reason',
        'cancelled_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
        ];
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function initialCoach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initial_coach_id');
    }

    public function coachChanges(): HasMany
    {
        return $this->hasMany(ScheduleCoachChange::class)->orderBy('created_at');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }
}
