<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventParticipant extends Model
{
    protected $fillable = [
        'event_id',
        'athlete_id',
        'attendance_status',
        'excused_certificate',
        'result_label',
        'result_place',
        'result_rank_id',
        'certificate_id',
        'result_description',
        'evidence_file_path',
        'results_filled_at',
    ];

    protected function casts(): array
    {
        return [
            'results_filled_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    public function resultRank(): BelongsTo
    {
        return $this->belongsTo(Rank::class, 'result_rank_id');
    }

    public function hasResults(): bool
    {
        return $this->results_filled_at !== null
            || $this->result_label
            || $this->result_place
            || $this->result_rank_id
            || $this->certificate_id
            || $this->result_description
            || $this->evidence_file_path;
    }
}
