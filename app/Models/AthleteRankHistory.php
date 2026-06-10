<?php

namespace App\Models;

use App\Support\DateFormatter;
use Illuminate\Database\Eloquent\Model;

class AthleteRankHistory extends Model
{
    protected $fillable = ['athlete_id', 'rank_id', 'assigned_at', 'event_participant_id'];

    protected $appends = ['assigned_at_display'];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'date:Y-m-d',
        ];
    }

    public function getAssignedAtDisplayAttribute(): ?string
    {
        return DateFormatter::toDisplayDate($this->assigned_at);
    }

    public function athlete()
    {
        return $this->belongsTo(Athlete::class);
    }

    public function rank()
    {
        return $this->belongsTo(Rank::class);
    }
}
