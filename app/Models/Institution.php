<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    protected $fillable = [
        'type',
        'name',
        'director_dat',
    ];

    public function athletes(): HasMany
    {
        return $this->hasMany(Athlete::class);
    }
}
