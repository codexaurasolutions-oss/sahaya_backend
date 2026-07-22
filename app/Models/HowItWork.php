<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HowItWork extends Model
{
    use HasFactory;

    public function howitworkDes()
    {
        return $this->hasOne(HowItWorkDescription::class, 'parent_id', 'id');
    }



}
