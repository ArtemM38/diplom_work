<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = [
        'group_id',
        'location_id',
        'coach_id',
        'lesson_date',
        'day_of_week',
        'start_time',
        'end_time',
        'lesson_type'
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
    public function location()
    {
        return $this->belongsTo(Location::class);
    }
    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}
