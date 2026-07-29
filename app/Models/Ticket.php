<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'zoho_ticket_id',
        'subject',
        'description',
        'category',
        'priority',
        'status',
        'image',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset($this->image);
        }
        return null;
    }
}
