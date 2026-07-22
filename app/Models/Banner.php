<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Banner extends Model
{
    use SoftDeletes;

    protected $table = 'banner';

    protected $fillable = [
        'user_id',
        'position',
        'image',
        'type',
        'extensions',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }


    public function getImageAttribute($value)
    {
        if ($value) {
             if ($this->extensions === 'url') {
            return $value;
        }
            return url($value); // full URL
        }
        return null;
    }

}
