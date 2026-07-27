<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPolicyAcceptance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'policy_type',
        'policy_version',
        'ip_address',
        'user_agent',
        'accepted_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
