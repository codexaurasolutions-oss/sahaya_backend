<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = [
        'user_id',
        'to',
        'from',
        'message',
        'status',
        'sid',
        'sent_at',
    ];

    // Optional: link to user
    // public function user()
    // {
    //     return $this->belongsTo(User::class);
    // }
}
