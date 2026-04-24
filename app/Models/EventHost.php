<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventHost extends Model
{
    protected $fillable = [
        'full_name',
        'rank',
        'city',
        'contacts',
        'extra_info',
    ];

    public function achievements()
    {
        return $this->hasMany(PortfolioAchievement::class);
    }
}
