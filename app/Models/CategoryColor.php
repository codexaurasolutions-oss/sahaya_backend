<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryColor extends Model
{
    use HasFactory;

    protected $table = 'category_colors';


    public function Colors(){
        return $this->hasOne(Color::class, 'id', 'color_id');
    }
}
