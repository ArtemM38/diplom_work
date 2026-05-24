<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioAchievement extends Model
{
    protected $fillable = [
        'athlete_id',
        'event_id',
        'event_name',
        'event_type_id',
        'event_place',
        'event_date',
        'event_period',
        'event_level_id',
        'event_host_id',
        'result_label',
        'result_place',
        'result_rank_id',
        'certificate_id',
        'result_description',
        'evidence_file_path',
    ];

    public function athlete()
    {
        return $this->belongsTo(Athlete::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function eventType()
    {
        return $this->belongsTo(EventType::class);
    }

    public function eventLevel()
    {
        return $this->belongsTo(EventLevel::class);
    }

    public function eventHost()
    {
        return $this->belongsTo(EventHost::class);
    }

    public function resultRank()
    {
        return $this->belongsTo(Rank::class, 'result_rank_id');
    }
}
