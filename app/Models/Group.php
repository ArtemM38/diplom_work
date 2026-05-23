<?php

namespace App\Models;

use App\Models\AthleteBalanceHistory;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'status', 'type', 'tariff_amount', 'archived_at'];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'tariff_amount' => 'decimal:2',
        ];
    }

    public function athletes(): BelongsToMany
    {
        return $this->belongsToMany(Athlete::class, 'athlete_group')->withPivot('training_price');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function hasTrainingHistory(): bool
    {
        if ($this->schedules()->exists()) {
            return true;
        }

        return AthleteBalanceHistory::query()
            ->whereHas('schedule', fn ($q) => $q->where('group_id', $this->id))
            ->exists();
    }

    public function scopeVisible($query)
    {
        return $query->whereNull('deleted_at')->where('status', '!=', 'archived');
    }
}
