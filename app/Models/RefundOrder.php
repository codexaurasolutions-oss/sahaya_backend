<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundOrder extends Model
{
    use HasFactory;

    protected $table = 'refund_orders';

   
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_number', 'order_number'); 
    }
    public function lookup()
    {
        return $this->belongsTo(Lookup::class, 'reason', 'id');
    }
    public function images()
    {
        return $this->hasMany(RefundOrderImage::class, 'refund_order_id', 'id');
    }

}
