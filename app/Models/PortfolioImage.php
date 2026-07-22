<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PortfolioImage extends Model
{
    use SoftDeletes;

    protected $table = 'portfolio_images';

    protected $fillable = [
        'user_id',
        'image',
    ];
    protected $appends = ['image_url']; // auto add image_url in response

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

      public function getImageUrlAttribute()
    {
        return $this->image ? url($this->image) : null;
    }
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
