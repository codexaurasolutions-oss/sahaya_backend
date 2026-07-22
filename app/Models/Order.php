<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    protected $table = 'orders';

    public function orderProduct()
    {
        return $this->hasMany(OrderProducts::class, 'order_number', 'order_number');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
 public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }


}
