<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AthleteFinance extends Model
{
    protected $fillable = [
        'athlete_id',
        'balance',
        'training_price',
    ];

    public function athlete()
    {
        return $this->belongsTo(Athlete::class);
    }
}
