<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AthleteDocument extends Model
{
    protected $fillable = [
        'athlete_id',
        'type',
        'identity_kind',
        'series',
        'number',
        'issued_by',
        'issue_date',
        'expiry_date',
        'file_path',
    ];

    public function athlete()
    {
        return $this->belongsTo(Athlete::class);
    }
}
