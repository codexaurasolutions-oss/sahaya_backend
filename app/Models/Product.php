<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $table = 'products';

    public function userDetails(){
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function parentCategoryDetails(){
        return $this->hasOne(Category::class, 'id', 'parent_category');
    }

    public function subCategoryDetails(){
        return $this->hasOne(Category::class, 'id', 'category_level_2');
    }

    public function prodcutColorDetails(){
        return $this->hasMany(ProductColor::class, 'product_id', 'id');
    }

    public function prodcutSizeDetails(){
        return $this->hasMany(ProductSize::class, 'product_id', 'id');
    }
    
    public function orderLogDetails()
{
    return $this->hasMany(OrderLogDetail::class);
}

}
