<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventType extends Model
{
    protected $fillable = ['name'];

    public function achievements()
    {
        return $this->hasMany(PortfolioAchievement::class);
    }
}
