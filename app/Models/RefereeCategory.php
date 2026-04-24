<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefereeCategory extends Model
{
    public $timestamps = false;

    protected $fillable = ['name'];

    public function athleteRefereeHistories()
    {
        return $this->hasMany(AthleteRefereeHistory::class);
    }
}
