<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegalConsent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone_number',
        'type',
        'ip_address',
        'user_agent',
        'consent_data',
        'accepted_at',
    ];

    protected $casts = [
        'consent_data' => 'array',
        'accepted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
