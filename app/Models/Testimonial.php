<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use HasFactory;

    public function TestimonialDescription()
    {
        return $this->hasMany(TestimonialDescription::class, 'parent_id', 'id');
    }

}
