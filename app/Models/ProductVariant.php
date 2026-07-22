<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;
    
    public function colorDetails(){
        return $this->hasOne(Color::class, 'id', 'color_id');
    }

    public function sizeDetails(){
        return $this->hasOne(Size::class, 'id', 'size_id');
    }

    

    public function product() {
        return $this->belongsTo(Product::class);
    }
    
}
