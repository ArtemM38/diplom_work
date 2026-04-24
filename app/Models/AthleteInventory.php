<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AthleteInventory extends Model
{
    protected $fillable = [
        'athlete_id',
        'weapon_case',
        'jo',
        'boken',
        'tanto',
        'tshirt',
        'olympic_jacket',
        'cap',
        'backpack',
        'shoe_bag',
        'budo_passport',
        'qual_book',
        'referee_book'
    ];
}
