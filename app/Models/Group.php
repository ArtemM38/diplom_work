<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = ['name', 'status', 'type', 'tariff_amount'];

    public function athletes()
    {
        return $this->belongsToMany(Athlete::class, 'athlete_group')->withPivot('training_price');
    }
}
