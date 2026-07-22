<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPetDetail extends Model
{
    use HasFactory;

    protected $table = 'user_pet_details';

    protected $fillable = [
        'user_id',
        'address_id',
        'pet_type',
        'pet_count'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function address()
    {
        return $this->belongsTo(UserAddress::class);
    }
}