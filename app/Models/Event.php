<?php

namespace App\Models;

use App\Support\DateFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = [
        'name',
        'cost',
        'event_type_id',
        'event_level_id',
        'event_place',
        'event_host_id',
        'event_date',
        'event_date_to',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'event_date' => 'date:Y-m-d',
            'event_date_to' => 'date:Y-m-d',
        ];
    }

    protected $appends = [
        'event_date_display',
        'event_date_range_display',
    ];

    public function getEventDateDisplayAttribute(): ?string
    {
        return DateFormatter::toDisplayDate($this->event_date);
    }

    public function getEventDateRangeDisplayAttribute(): ?string
    {
        $from = DateFormatter::toDisplayDate($this->event_date);
        $to = DateFormatter::toDisplayDate($this->event_date_to);

        if ($from && $to && $to !== $from) {
            return "{$from} — {$to}";
        }

        return $from;
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    public function eventLevel(): BelongsTo
    {
        return $this->belongsTo(EventLevel::class);
    }

    public function eventHost(): BelongsTo
    {
        return $this->belongsTo(EventHost::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }

}
