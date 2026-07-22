<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSize extends Model
{
    use HasFactory;
    public function sizeDetails(){
        return $this->hasOne(Size::class, 'id', 'size_id');
    }
}
