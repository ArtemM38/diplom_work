<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guardian extends Model
{
    protected $fillable = ['user_id', 'full_name', 'phone', 'relation'];
    public function athletes()
    {
        return $this->belongsToMany(Athlete::class, 'athlete_guardian');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
