<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Size extends Model
{
    use HasFactory;
    protected $table = 'size';

    public function SizesDescription(){
        return $this->hasOne(SizeDescription::class, 'parent_id', 'id');
    }
}
