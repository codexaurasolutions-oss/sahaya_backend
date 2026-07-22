<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'job_id',
        'application_id',
        'status',
        'read_at',
    ];

    protected $casts = [
        'job_id' => 'integer',
        'application_id' => 'integer',
        'read_at' => 'datetime',
    ];
}
