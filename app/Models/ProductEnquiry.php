<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductEnquiry extends Model
{
    use HasFactory;
    protected $table = 'product_enquires';

    protected $fillable = [
        'is_read',
    ];

    public function sender()
    {
        return $this->hasOne(User::class, 'id', 'sender_id');
    }

    public function reciever()
    {
        return $this->hasOne(User::class, 'id', 'reciever_id');
    }

    public function product(){
        return $this->hasOne(Product::class,'id','product_id');
    }

    public function ProductVarientDetails()
    {
        return $this->hasOne(ProductVariant::class, 'product_id', 'product_id');
    }
    public function ProductDetails()
    {
        return $this->hasOne(Product::class, 'id', 'product_id');
    }
   
}
