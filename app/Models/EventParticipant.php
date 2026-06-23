<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function evidenceFiles(): HasMany
    {
        return $this->hasMany(EventParticipantEvidenceFile::class);
    }

    public function hasResults(): bool
    {
        $hasEvidence = $this->relationLoaded('evidenceFiles')
            ? $this->evidenceFiles->isNotEmpty()
            : $this->evidenceFiles()->exists();

        return $this->results_filled_at !== null
            || $this->result_label
            || $this->result_place
            || $this->result_rank_id
            || $this->certificate_id
            || $this->result_description
            || $hasEvidence;
    }
}
