<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrderProducts extends Model
{
    protected $table = 'order_products';

    protected $fillable = [
        'order_status',
    ];

    public function ProductDetails()
    {
        return $this->hasOne(Product::class, 'id', 'product_id');
    }

    public function ProductVarientDetails()
    {
        return $this->hasOne(ProductVariant::class, 'id', 'product_varient_id');
    }
}
