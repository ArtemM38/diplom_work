<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'registration_address',
        'photo',
        'school_name',
        'school_director_dat',
        'school_class',
        'work_place',
        'work_position'
    ];

    // Связи
    public function user()
    {
        return $this->belongsTo(User::class);
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
    public function inventory()
    {
        return $this->hasOne(AthleteInventory::class);
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
}
