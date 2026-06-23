<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Athlete extends Model
{
    protected $fillable = [
        'user_id',
        'last_name_nom',
        'first_name_nom',
        'middle_name_nom',
        'full_name_gen',
        'full_name_dat',
        'full_name_ins',
        'phone',
        'birth_date',
        'gender',
        'occupation_type',
        'institution_id',
        'registration_address',
        'photo',
        'school_class',
        'work_position',
    ];

    protected $appends = [
        'school_name',
        'school_director_dat',
        'kindergarten_name',
        'work_place',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function rankHistories()
    {
        return $this->hasMany(AthleteRankHistory::class);
    }

    public function refereeHistories()
    {
        return $this->hasMany(AthleteRefereeHistory::class);
    }

    public function documents()
    {
        return $this->hasMany(AthleteDocument::class);
    }

    public function inventoryItems()
    {
        return $this->belongsToMany(InventoryItem::class, 'athlete_inventory');
    }

    public function guardians()
    {
        return $this->belongsToMany(Guardian::class, 'athlete_guardian');
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'athlete_group');
    }

    public function achievements()
    {
        return $this->hasMany(PortfolioAchievement::class);
    }

    public function finance()
    {
        return $this->hasOne(AthleteFinance::class);
    }

    public function balanceHistory()
    {
        return $this->hasMany(AthleteBalanceHistory::class);
    }

    public function getSchoolNameAttribute(): ?string
    {
        return $this->occupation_type === 'study' ? $this->institution?->name : null;
    }

    public function getSchoolDirectorDatAttribute(): ?string
    {
        return $this->occupation_type === 'study' ? $this->institution?->director_dat : null;
    }

    public function getKindergartenNameAttribute(): ?string
    {
        return $this->occupation_type === 'kindergarten' ? $this->institution?->name : null;
    }

    public function getWorkPlaceAttribute(): ?string
    {
        return $this->occupation_type === 'work' ? $this->institution?->name : null;
    }
}
