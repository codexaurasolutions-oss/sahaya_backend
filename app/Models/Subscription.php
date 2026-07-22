<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use SoftDeletes;

    protected $table = 'subscriptions';

    protected $fillable = [
        'subscription_name',
        'description',
        'price',
        'validity',
        'type',
        'razorpay_order_id',
        'extra',
        'role_id',
        'subscription_limit',
        'job_limit',
        'staff_limit',
        'extra_job_price',
        'extra_staff_price'
    ];

    protected $casts = [
        'extra' => 'array', // JSON column as array
    ];
}
