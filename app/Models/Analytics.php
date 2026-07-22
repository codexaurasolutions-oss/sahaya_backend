<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Analytics extends Model
{
    protected $fillable = [
        'user_id',
        'spend_this_month',
        'saved_this_month',
        'total_bookings',
        'favorite_providers',
        'cashback_earned',
    ];
}
