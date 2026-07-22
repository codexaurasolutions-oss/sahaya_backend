<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lookup extends Model
{
    use HasFactory;

    public function LookupDiscription($value='')
    {
        return $this->hasOne(LookupDiscription::class, 'parent_id', 'id');
    }
}
