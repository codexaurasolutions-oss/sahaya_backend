<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralRedemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'points_redeemed',
        'credits_granted',
        'points_per_credit',
    ];

    protected $casts = [
        'points_redeemed' => 'decimal:2',
        'credits_granted' => 'integer',
        'points_per_credit' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
