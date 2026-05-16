<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AthleteBalanceHistory extends Model
{
    protected $fillable = [
        'athlete_id',
        'schedule_id',
        'attendance_id',
        'change_amount',
        'balance_before',
        'balance_after',
        'reason',
        'status',
        'changed_by',
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
