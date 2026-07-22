<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    protected $fillable = [
        'user_id',
        'vendorId',
    ];

    // // Relationships
    // public function service()
    // {
    //     return $this->belongsTo(Service::class, 'service_id');
    // }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
     public function vendor()
    {
        return $this->belongsTo(User::class, 'vendorId');
    }

}
