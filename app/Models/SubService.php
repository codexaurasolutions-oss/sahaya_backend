<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubService extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'image',
    ];

        protected $appends = ['image_url'];

    // Accessor for full image URL
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return url($this->image); // generates full URL
        }
        return null; // if no image
    }


    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
