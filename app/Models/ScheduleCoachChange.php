<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleCoachChange extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'schedule_id',
        'from_coach_id',
        'to_coach_id',
        'changed_by_user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function fromCoach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_coach_id');
    }

    public function toCoach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_coach_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
