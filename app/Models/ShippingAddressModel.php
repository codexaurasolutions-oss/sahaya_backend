<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ShippingAddressModel extends Model
{
    protected $table = 'shipping_address';



    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
