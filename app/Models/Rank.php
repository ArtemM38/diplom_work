<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rank extends Model
{
    public $timestamps = false;

    protected $fillable = ['name'];

    public function athleteRankHistories()
    {
        return $this->hasMany(AthleteRankHistory::class);
    }
}
