<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AthleteRefereeHistory extends Model
{
    protected $fillable = ['athlete_id', 'referee_category_id', 'assigned_at'];

    public function athlete()
    {
        return $this->belongsTo(Athlete::class);
    }

    public function refereeCategory()
    {
        return $this->belongsTo(RefereeCategory::class);
    }
}
