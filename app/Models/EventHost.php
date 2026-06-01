<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventHost extends Model
{
    protected $fillable = [
        'full_name',
        'birth_date',
        'rank',
        'city',
        'contacts',
        'extra_info',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
        ];
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function achievements()
    {
        return $this->hasMany(PortfolioAchievement::class);
    }
}
