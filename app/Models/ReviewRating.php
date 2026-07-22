<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewRating extends Model
{
    use HasFactory;
    protected $table = 'review_ratings';

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function product(){
        return $this->hasOne(Product::class,'id','product_id');
    }

    public function ProductVarientDetails()
    {
        return $this->hasOne(ProductVariant::class, 'id', 'product_varient_id');
    }
   
}
