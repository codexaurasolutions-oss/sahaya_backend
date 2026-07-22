<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;
    protected $table = 'coupons';

    public function userDetails(){
        return $this->hasOne(User::class, 'id', 'user_id');
    }
    protected $fillable = [
        'code',
        'short_title',
        'title',
        'type',
        'is_per',
        'is_amount',
        'quantity',
        'per_person_use',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'status',
        'min_amount',
        'is_deleted',
        'max_uses',
    ];
}
