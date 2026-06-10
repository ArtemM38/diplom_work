<?php

namespace App\Models;

use App\Support\DateFormatter;
use Illuminate\Database\Eloquent\Model;

class AthleteRefereeHistory extends Model
{
    protected $fillable = ['athlete_id', 'referee_category_id', 'assigned_at'];

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

    public function refereeCategory()
    {
        return $this->belongsTo(RefereeCategory::class);
    }
}
