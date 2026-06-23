<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\StorageUrl;

class EventParticipantEvidenceFile extends Model
{
    protected $fillable = [
        'event_participant_id',
        'file_path',
        'original_name',
    ];

    protected $appends = [
        'url',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class, 'event_participant_id');
    }

    public function getUrlAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return StorageUrl::url($this->file_path);
    }
}
