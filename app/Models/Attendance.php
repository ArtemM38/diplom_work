<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'schedule_id',
        'athlete_id',
        'status'
    ];

    public function athlete()
    {
        return $this->belongsTo(Athlete::class);
    }
    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }
}
