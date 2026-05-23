<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AthleteFinance extends Model
{
    protected $fillable = [
        'athlete_id',
        'balance',
        'discount_percent',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'discount_percent' => 'integer',
        ];
    }

    public function athlete()
    {
        return $this->belongsTo(Athlete::class);
    }
}
