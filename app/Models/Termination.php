<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Termination extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reason',
        'termination_date',
        'notice_period_days',
        'status',
        'approved_by',
        'remarks',
        'is_blacklist',
        'reported_by',
        'police_station_name',
        'police_station_contact',
        'police_station_address',
        'fir_photo',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

}
