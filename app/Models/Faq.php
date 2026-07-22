<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory;

    public function faqDiscription($value='')
    {
        return $this->hasOne(Faq_description::class, 'parent_id', 'id');
    }
}
