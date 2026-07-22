<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuitJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'user_id',
        'end_date',
        'reason',
        'status'
    ];

    protected $casts = [
        'end_date' => 'date',
    ];

    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
