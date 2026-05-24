<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AthleteRankHistory extends Model
{
    protected $fillable = ['athlete_id', 'rank_id', 'assigned_at', 'event_participant_id'];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'date',
        ];
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
